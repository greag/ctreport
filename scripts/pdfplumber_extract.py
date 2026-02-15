import logging
import sys
import pdfplumber


def main() -> int:
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
    logging.getLogger("pdfminer").setLevel(logging.ERROR)

    if len(sys.argv) < 2:
        print("Usage: pdfplumber_extract.py <pdf_path>", file=sys.stderr)
        return 2

    pdf_path = sys.argv[1]

    parts = []
    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text = page.extract_text(
                x_tolerance=2,
                y_tolerance=2,
                layout=True,
                keep_blank_chars=False,
            )
            if text is None:
                text = ""
            parts.append(text)

    sys.stdout.write("\n--- PAGE BREAK ---\n".join(parts))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
