# ============================================
# CGP Project - Flask Application Dockerfile
# ============================================

# استخدام Python 3.11 slim (خفيف وسريع)
FROM python:3.11-slim

# معلومات المشروع
LABEL maintainer="Mohsin Allawati"
LABEL project="cgp-project"
LABEL description="Career Guidance Platform - Flask App"

# منع Python من إنشاء ملفات .pyc وتفعيل output مباشر
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1
ENV FLASK_APP=app.py
ENV PORT=5050

# إنشاء working directory داخل الـ container
WORKDIR /app

# تثبيت system dependencies (لو احتجنا أي شي مستقبلاً)
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# نسخ requirements.txt أولاً (للاستفادة من Docker cache)
COPY requirements.txt .

# تثبيت Python dependencies
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt && \
    pip install --no-cache-dir gunicorn

# نسخ كل ملفات المشروع
COPY . .

# إنشاء مجلد database لو ما كان موجود
RUN mkdir -p /app/database

# فتح البورت 5050
EXPOSE 5050

# Health check للتأكد إن التطبيق شغّال
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:5050/')" || exit 1

# تشغيل التطبيق باستخدام gunicorn (production-ready)
# لو تبي development mode بدّل بـ: CMD ["python", "app.py"]
CMD ["gunicorn", "--bind", "0.0.0.0:5050", "--workers", "2", "--timeout", "120", "--access-logfile", "-", "app:create_app()"]
