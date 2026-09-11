import logging

# Configurar logging
logging.basicConfig(level=logging.DEBUG, format='%(asctime)s - %(levelname)s - %(message)s')

def test_logging():
    logging.debug("Mensaje de debug: Entrando en la función test_logging.")
    logging.info("Mensaje de info: Si ves esto, el logging está funcionando.")

if __name__ == "__main__":
    test_logging()
