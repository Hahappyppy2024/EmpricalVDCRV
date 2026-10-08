FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    FLASK_APP=run.py

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

RUN mkdir -p data/uploads

EXPOSE 8000

CMD ["sh", "-c", "flask --app run:app init-db && flask --app run:app run --host 0.0.0.0 --port 8000"]
