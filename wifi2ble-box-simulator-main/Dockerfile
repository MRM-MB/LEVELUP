FROM python:3.12-slim

WORKDIR /app

COPY . .

EXPOSE 8000

CMD ["python", "simulator/main.py", "--port", "8000", "--desks", "100"]
