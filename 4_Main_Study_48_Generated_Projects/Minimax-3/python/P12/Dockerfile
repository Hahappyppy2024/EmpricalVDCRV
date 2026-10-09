FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install -r requirements.txt

COPY app ./app
COPY scripts ./scripts
COPY run.py ./
COPY .env.example ./.env.example

RUN mkdir -p /app/data/uploads /app/data/exports

EXPOSE 5000

ENV FLASK_DEBUG=0 \
    HOST=0.0.0.0 \
    PORT=5000

CMD ["sh", "-c", "python scripts/init_db.py && python run.py"]
