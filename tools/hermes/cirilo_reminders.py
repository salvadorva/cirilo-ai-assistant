#!/usr/bin/env python3
"""Cliente mínimo de Hermes para los recordatorios contextuales de Cirilo (RC4).

No es un agente: no espera, no programa nada localmente y no observa el
teléfono. Solo crea, consulta, cancela, pospone y completa recordatorios
ya acordados con Salva. Una vez que Cirilo responde 201, el recordatorio ya
no depende de esta terminal.

Configuración (nunca por argumentos de línea de comandos):
  CIRILO_HERMES_BASE_URL          https://<host>  (HTTPS obligatorio; http solo para localhost)
  CIRILO_HERMES_REMINDERS_TOKEN   credencial exclusiva; si no está en el entorno se lee
                                  de ~/.secrets (formato KEY=VALUE, permisos 0600)
  CIRILO_HERMES_STATE_DIR         opcional; por defecto ~/.local/state/cirilo-hermes

Códigos de salida: 0 éxito confirmado · 2 uso/configuración · 3 rechazo del
servidor (4xx) · 4 error del servidor (5xx) · 75 resultado INCIERTO (repetir con
`retry`; nunca crear otra petición con otra clave).
"""

from __future__ import annotations

import argparse
import json
import os
import secrets
import stat
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

TOKEN_VAR = "CIRILO_HERMES_REMINDERS_TOKEN"
API_PATH = "/api/integrations/hermes/v1/reminders"
TIMEOUT_SECONDS = 15
EXIT_OK, EXIT_USAGE, EXIT_REJECTED, EXIT_SERVER, EXIT_UNCERTAIN = 0, 2, 3, 4, 75


class ClientError(Exception):
    def __init__(self, message: str, exit_code: int = EXIT_USAGE):
        super().__init__(message)
        self.exit_code = exit_code


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    """Nunca seguir redirecciones: la credencial no debe viajar a otro destino."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ClientError(f"Redirección {code} rechazada; revisa CIRILO_HERMES_BASE_URL.", EXIT_REJECTED)


def load_token(environ=os.environ, home: Path | None = None) -> str:
    token = environ.get(TOKEN_VAR)
    if token:
        return token.strip()
    path = (home or Path.home()) / ".secrets"
    if not path.is_file():
        raise ClientError(f"Falta {TOKEN_VAR} (entorno o ~/.secrets).")
    mode = stat.S_IMODE(path.stat().st_mode)
    if mode & (stat.S_IRWXG | stat.S_IRWXO):
        raise ClientError("~/.secrets debe tener permisos 0600; no se leyó.")
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line.startswith("export "):
            line = line[len("export "):]
        key, sep, value = line.partition("=")
        if sep and key.strip() == TOKEN_VAR:
            return value.strip().strip("'\"")
    raise ClientError(f"{TOKEN_VAR} no está definido en ~/.secrets.")


def base_url(environ=os.environ) -> str:
    url = environ.get("CIRILO_HERMES_BASE_URL", "").rstrip("/")
    parsed = urllib.parse.urlparse(url)
    local = parsed.hostname in ("localhost", "127.0.0.1")
    if parsed.scheme != "https" and not (parsed.scheme == "http" and local):
        raise ClientError("CIRILO_HERMES_BASE_URL debe ser HTTPS (http solo para localhost).")
    if parsed.query or parsed.fragment or parsed.username or parsed.password:
        raise ClientError("CIRILO_HERMES_BASE_URL no admite credenciales, query ni fragmento.")
    return url


class Client:
    def __init__(self, url: str, token: str, state_dir: Path):
        self.url = url
        self._token = token
        self.state_dir = state_dir
        self._opener = urllib.request.build_opener(_NoRedirect)

    def __repr__(self) -> str:  # nunca exponer la credencial
        return f"Client(url={self.url!r})"

    # ---- persistencia de operaciones en curso (fuera del vault) ----

    def _pending_dir(self) -> Path:
        path = self.state_dir / "pending"
        path.mkdir(parents=True, exist_ok=True, mode=0o700)
        os.chmod(self.state_dir, 0o700)
        os.chmod(path, 0o700)
        return path

    def _save_pending(self, operation: dict) -> Path:
        path = self._pending_dir() / f"{operation['key']}.json"
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, "w", encoding="utf-8") as handle:
            json.dump(operation, handle, ensure_ascii=False)
        return path

    # ---- HTTP ----

    def request(self, method: str, path: str, body: dict | None = None, key: str | None = None) -> tuple[int, dict]:
        data = None if body is None else json.dumps(body, ensure_ascii=False).encode("utf-8")
        request = urllib.request.Request(self.url + path, data=data, method=method)
        request.add_header("Authorization", "Bearer " + self._token)
        request.add_header("Accept", "application/json")
        if data is not None:
            request.add_header("Content-Type", "application/json")
        if key:
            request.add_header("Idempotency-Key", key)
        try:
            with self._opener.open(request, timeout=TIMEOUT_SECONDS) as response:
                return response.status, json.loads(response.read() or b"{}")
        except urllib.error.HTTPError as error:
            try:
                payload = json.loads(error.read() or b"{}")
            except ValueError:
                payload = {}
            finally:
                error.close()
            return error.code, payload
        except ClientError:
            raise
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            raise ClientError(f"Sin respuesta confirmada ({type(error).__name__}).", EXIT_UNCERTAIN) from None

    def mutate(self, method: str, path: str, body: dict) -> tuple[int, dict]:
        """Guarda clave y petición antes de enviar; ante timeout se repite igual."""
        operation = {"key": secrets.token_urlsafe(24), "method": method, "path": path, "body": body}
        return self.replay(operation, self._save_pending(operation))

    def replay(self, operation: dict, pending: Path) -> tuple[int, dict]:
        status, payload = self.request(operation["method"], operation["path"], operation["body"], operation["key"])
        retry_later = status >= 500 or status == 429 or payload.get("code") == "idempotency_conflict"
        if not retry_later:
            pending.unlink(missing_ok=True)  # resultado definitivo (éxito o rechazo): no hay nada que repetir
        return status, payload

    def retry(self, key: str) -> tuple[int, dict]:
        pending = self._pending_dir() / f"{key}.json"
        if not pending.is_file():
            raise ClientError("No hay una operación pendiente con esa clave.")
        return self.replay(json.loads(pending.read_text(encoding="utf-8")), pending)

    def pending_operations(self) -> list[str]:
        return sorted(p.stem for p in self._pending_dir().glob("*.json"))


def _exit_code(status: int) -> int:
    if 200 <= status < 300:
        return EXIT_OK
    return EXIT_SERVER if status >= 500 else EXIT_REJECTED


def _summary(status: int, payload: dict) -> str:
    if status == 201:  # alta persistida, también al confirmar un retry
        return (f"Cirilo lo guardó; ya no depende de esta terminal. id={payload.get('id')} "
                f"hora={payload.get('scheduled_at')} caduca={payload.get('expires_at')} versión={payload.get('version')}")
    if 200 <= status < 300:
        return f"Confirmado por Cirilo: estado={payload.get('state')} versión={payload.get('version')}"
    return f"NO confirmado (HTTP {status}, {payload.get('code', 'sin código')}): {payload.get('message', '')}".strip()


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="cirilo_reminders", description="Recordatorios acordados con Salva (Cirilo).")
    sub = parser.add_subparsers(dest="command", required=True)
    create = sub.add_parser("create", help="Crear desde un acuerdo explícito (JSON en --file o stdin)")
    create.add_argument("--file", help="JSON del contrato v1; '-' para stdin", default="-")
    sub.add_parser("pending", help="Listar pendientes")
    show = sub.add_parser("show", help="Detalle y estado de despacho")
    show.add_argument("id")
    for action in ("complete", "cancel"):
        cmd = sub.add_parser(action)
        cmd.add_argument("id")
        cmd.add_argument("--version", type=int, required=True)
    snooze = sub.add_parser("snooze", help="Posponer 15/30/60 min o a una hora absoluta acordada")
    snooze.add_argument("id")
    snooze.add_argument("--version", type=int, required=True)
    group = snooze.add_mutually_exclusive_group(required=True)
    group.add_argument("--minutes", type=int, choices=(15, 30, 60))
    group.add_argument("--at", help="ISO 8601 con offset, p. ej. 2026-09-24T11:00:00-06:00")
    retry = sub.add_parser("retry", help="Repetir exactamente una operación incierta")
    retry.add_argument("key")
    sub.add_parser("uncertain", help="Listar operaciones inciertas guardadas")
    return parser


def main(argv: list[str] | None = None, environ=os.environ, stdin=sys.stdin, stdout=sys.stdout, stderr=sys.stderr) -> int:
    args = build_parser().parse_args(argv)
    try:
        state = Path(environ.get("CIRILO_HERMES_STATE_DIR") or Path.home() / ".local/state/cirilo-hermes")
        client = Client(base_url(environ), load_token(environ), state)
        if args.command == "create":
            raw = stdin.read() if args.file == "-" else Path(args.file).read_text(encoding="utf-8")
            status, payload = client.mutate("POST", API_PATH, json.loads(raw))
        elif args.command == "pending":
            status, payload = client.request("GET", API_PATH + "?state=pending")
        elif args.command == "show":
            status, payload = client.request("GET", f"{API_PATH}/{urllib.parse.quote(args.id)}")
        elif args.command in ("complete", "cancel"):
            status, payload = client.mutate("POST", f"{API_PATH}/{urllib.parse.quote(args.id)}/{args.command}", {"expected_version": args.version})
        elif args.command == "snooze":
            body = {"expected_version": args.version}
            body.update({"minutes": args.minutes} if args.minutes else {"scheduled_at": args.at})
            status, payload = client.mutate("POST", f"{API_PATH}/{urllib.parse.quote(args.id)}/snooze", body)
        elif args.command == "retry":
            status, payload = client.retry(args.key)
        else:
            print(json.dumps(client.pending_operations()), file=stdout)
            return EXIT_OK
    except ClientError as error:
        pending = ""
        if error.exit_code == EXIT_UNCERTAIN:
            pending = " Operación guardada: repite con `retry <clave>` (ver `uncertain`)."
        print(f"INCIERTO: {error}{pending}" if error.exit_code == EXIT_UNCERTAIN else f"ERROR: {error}", file=stderr)
        return error.exit_code
    except (ValueError, OSError) as error:
        print(f"ERROR: entrada inválida ({type(error).__name__}).", file=stderr)
        return EXIT_USAGE

    print(json.dumps(payload, ensure_ascii=False, indent=2), file=stdout)
    print(_summary(status, payload), file=stderr)
    return _exit_code(status)


if __name__ == "__main__":
    sys.exit(main())
