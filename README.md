# PDF_LMS6.0 (Laravel 9, non-LLM)

Converts CIBIL PDF reports to Text/JSON/Excel using Ghostscript + Tesseract (no LLM).

## Prerequisites
- PHP 8.1+
- Ghostscript installed
- Tesseract OCR installed

## Setup
1. `composer install`
2. Copy `.env.example` → `.env`
3. Set `GHOSTSCRIPT_PATH` and `TESSERACT_PATH` if not on PATH
4. Run:
   - `php artisan serve --port=8020`

## Usage
- UI: open `http://127.0.0.1:8020`
- API: `POST /api/process` with form-data `pdf`, `user_id`, optional `password`
- Downloads:
  - `GET /api/process/{token}/text`
  - `GET /api/process/{token}/json`
  - `GET /api/process/{token}/xlsx`
