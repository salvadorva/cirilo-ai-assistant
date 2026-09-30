"""Pruebas del cliente Hermes contra un servidor local simulado (sin red externa ni credenciales reales)."""

import io
import json
import os
import stat
import sys
import tempfile
import threading
import time
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import cirilo_reminders as cli  # noqa: E402

FIXTURES = Path(__file__).resolve().parents[2] / "tests/Fixtures/contextual-reminders"
FAKE_TOKEN = "cirilo_hrm_FAKE_TOKEN_FOR_TESTS_ONLY"


def fixture(name):
    return json.loads((FIXTURES / name).read_text(encoding="utf-8"))


class Stub(BaseHTTPRequestHandler):
    requests = []
    script = []  # respuestas programadas: (status, body, delay, headers)

    def log_message(self, *args):
        pass

    def _handle(self):
        length = int(self.headers.get("Content-Length") or 0)
        body = json.loads(self.rfile.read(length)) if length else None
        Stub.requests.append({"method": self.command, "path": self.path, "body": body, "headers": dict(self.headers)})
        status, payload, delay, headers = Stub.script.pop(0) if Stub.script else (200, {}, 0, {})
        time.sleep(delay)
        try:
            self.send_response(status)
            for key, value in headers.items():
                self.send_header(key, value)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(json.dumps(payload).encode())
        except (BrokenPipeError, ConnectionResetError):
            pass

    do_GET = do_POST = _handle


class ClientTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Stub)
        threading.Thread(target=cls.server.serve_forever, daemon=True).start()
        cls.url = f"http://127.0.0.1:{cls.server.server_port}"

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        Stub.requests, Stub.script = [], []
        self.tmp = tempfile.TemporaryDirectory()
        self.env = {"CIRILO_HERMES_BASE_URL": self.url, cli.TOKEN_VAR: FAKE_TOKEN, "CIRILO_HERMES_STATE_DIR": self.tmp.name + "/state"}

    def tearDown(self):
        self.tmp.cleanup()

    def run_cli(self, argv, stdin=""):
        out, err = io.StringIO(), io.StringIO()
        code = cli.main(argv, environ=self.env, stdin=io.StringIO(stdin), stdout=out, stderr=err)
        return code, out.getvalue(), err.getvalue()

    def pending_files(self):
        path = Path(self.env["CIRILO_HERMES_STATE_DIR"]) / "pending"
        return sorted(path.glob("*.json")) if path.exists() else []

    # ---- credencial y configuración ----

    def test_token_from_secrets_file_requires_0600_and_is_never_printed(self):
        home = Path(self.tmp.name)
        secrets_file = home / ".secrets"
        secrets_file.write_text(f"OTRA=1\nexport {cli.TOKEN_VAR}='{FAKE_TOKEN}'\n")
        os.chmod(secrets_file, 0o644)
        with self.assertRaises(cli.ClientError):
            cli.load_token({}, home)
        os.chmod(secrets_file, 0o600)
        self.assertEqual(FAKE_TOKEN, cli.load_token({}, home))

        Stub.script = [(200, {"data": [], "meta": {}}, 0, {})]
        code, out, err = self.run_cli(["pending"])
        self.assertEqual(0, code)
        self.assertNotIn(FAKE_TOKEN, out + err)
        self.assertNotIn(FAKE_TOKEN, repr(cli.Client(self.url, FAKE_TOKEN, Path(self.tmp.name))))
        self.assertEqual("Bearer " + FAKE_TOKEN, Stub.requests[0]["headers"]["Authorization"])

    def test_https_is_required_except_localhost(self):
        for url in ("http://cirilo.example.com", "https://user:pw@cirilo.example.com", "https://cirilo.example.com/?x=1", ""):
            with self.assertRaises(cli.ClientError):
                cli.base_url({"CIRILO_HERMES_BASE_URL": url})
        self.assertEqual("https://cirilo.example.com", cli.base_url({"CIRILO_HERMES_BASE_URL": "https://cirilo.example.com/"}))

    # ---- alta y respuestas ----

    def test_create_sends_contract_with_idempotency_key_and_confirms_only_on_201(self):
        Stub.script = [(201, fixture("hermes-create-response.json"), 0, {})]
        code, out, err = self.run_cli(["create"], stdin=json.dumps(fixture("hermes-create-request.json")))

        self.assertEqual(0, code)
        self.assertIn("Cirilo lo guardó; ya no depende de esta terminal", err)
        sent = Stub.requests[0]
        self.assertEqual(("POST", cli.API_PATH), (sent["method"], sent["path"]))
        self.assertEqual(fixture("hermes-create-request.json"), sent["body"])
        self.assertRegex(sent["headers"]["Idempotency-Key"], r"^[A-Za-z0-9_\-]{16,100}$")
        self.assertEqual([], self.pending_files())

    def test_rejections_are_never_reported_as_scheduled(self):
        for status, code_name in ((409, "device_not_ready"), (422, "validation_failed"), (401, "unauthenticated")):
            Stub.script = [(status, {"code": code_name, "message": "x"}, 0, {})]
            code, out, err = self.run_cli(["create"], stdin=json.dumps(fixture("hermes-create-request.json")))
            self.assertEqual(cli.EXIT_REJECTED, code)
            self.assertNotIn("guardó", err)
            self.assertIn(code_name, err)

    def test_timeout_is_uncertain_and_retry_repeats_same_key_and_body(self):
        cli.TIMEOUT_SECONDS, original = 0.3, cli.TIMEOUT_SECONDS
        try:
            Stub.script = [(201, fixture("hermes-create-response.json"), 0.8, {})]
            code, out, err = self.run_cli(["create"], stdin=json.dumps(fixture("hermes-create-request.json")))
        finally:
            cli.TIMEOUT_SECONDS = original
        self.assertEqual(cli.EXIT_UNCERTAIN, code)
        self.assertIn("INCIERTO", err)
        self.assertNotIn("guardó", err)
        [pending] = self.pending_files()
        self.assertEqual(0o600, stat.S_IMODE(pending.stat().st_mode))
        self.assertEqual(0o700, stat.S_IMODE(pending.parent.stat().st_mode))

        time.sleep(0.6)
        Stub.script = [(201, fixture("hermes-create-response.json"), 0, {"Idempotent-Replayed": "true"})]
        code, out, err = self.run_cli(["retry", pending.stem])
        self.assertEqual(0, code)
        self.assertIn("Cirilo lo guardó", err)
        first, second = Stub.requests[0], Stub.requests[-1]
        self.assertEqual(first["headers"]["Idempotency-Key"], second["headers"]["Idempotency-Key"])
        self.assertEqual(first["body"], second["body"])
        self.assertEqual([], self.pending_files())

    def test_server_errors_rate_limits_and_in_progress_keep_the_operation_for_retry(self):
        for status, body in ((503, {}), (429, {"code": "rate_limited"}), (409, {"code": "idempotency_conflict"})):
            Stub.script = [(status, body, 0, {})]
            self.run_cli(["cancel", "a2d0e64a-6445-49e2-8c8d-6281e3cda714", "--version", "1"])
        self.assertEqual(3, len(self.pending_files()))
        code, out, err = self.run_cli(["uncertain"])
        self.assertEqual(3, len(json.loads(out)))

    def test_redirects_are_not_followed_and_do_not_leak_the_credential(self):
        Stub.script = [(302, {}, 0, {"Location": self.url + "/elsewhere"})]
        code, out, err = self.run_cli(["pending"])
        self.assertEqual(cli.EXIT_REJECTED, code)
        self.assertEqual(1, len(Stub.requests))

    def test_actions_build_exact_bodies(self):
        rid = "a2d0e64a-6445-49e2-8c8d-6281e3cda714"
        Stub.script = [(200, {"state": "pending", "version": 2}, 0, {})] * 3
        self.run_cli(["snooze", rid, "--version", "1", "--minutes", "30"])
        self.run_cli(["snooze", rid, "--version", "2", "--at", "2026-09-24T11:00:00-06:00"])
        self.run_cli(["complete", rid, "--version", "3"])
        self.assertEqual([
            (f"{cli.API_PATH}/{rid}/snooze", {"expected_version": 1, "minutes": 30}),
            (f"{cli.API_PATH}/{rid}/snooze", {"expected_version": 2, "scheduled_at": "2026-09-24T11:00:00-06:00"}),
            (f"{cli.API_PATH}/{rid}/complete", {"expected_version": 3}),
        ], [(r["path"], r["body"]) for r in Stub.requests])
        with self.assertRaises(SystemExit):
            self.run_cli(["snooze", rid, "--version", "1", "--minutes", "20"])


if __name__ == "__main__":
    unittest.main()
