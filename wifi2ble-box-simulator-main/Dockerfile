FROM python:3.12-slim

WORKDIR /app

COPY . .

EXPOSE 8000

CMD ["sh", "-c", "python simulator/main.py --port ${PORT:-8000} --desks 100"]
