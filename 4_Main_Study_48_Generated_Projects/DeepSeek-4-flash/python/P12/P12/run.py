"""Application entry point.

Run with:  flask --app run:app run  --port 8000
or:        python run.py
"""
import os

from app import create_app

app = create_app()

if __name__ == "__main__":
    port = int(os.environ.get("PORT", "8000"))
    app.run(host="0.0.0.0", port=port, debug=False)
