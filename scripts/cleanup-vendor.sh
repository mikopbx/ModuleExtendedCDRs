#!/bin/bash
# Cleanup script for vendor directory
# Removes unnecessary fonts and test files to reduce module size

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENDOR_DIR="$(dirname "$SCRIPT_DIR")/vendor"

if [ ! -d "$VENDOR_DIR" ]; then
    echo "Vendor directory not found: $VENDOR_DIR"
    exit 0
fi

echo "Cleaning up vendor directory..."

# mPDF fonts - keep only DejaVu, Free* and ocrb10 (needed for Cyrillic/Latin)
TTFONTS_DIR="$VENDOR_DIR/mpdf/mpdf/ttfonts"
if [ -d "$TTFONTS_DIR" ]; then
    echo "Removing unnecessary mPDF fonts..."

    # CJK fonts (Chinese, Japanese, Korean) - ~45MB
    rm -f "$TTFONTS_DIR/Sun-ExtA.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Sun-ExtB.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/UnBatang_0613.ttf" 2>/dev/null

    # Tibetan, Arabic, Thai, etc.
    rm -f "$TTFONTS_DIR/Jomolhari.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/ayar.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Abyssinica_SIL.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/DBSILBR.ttf" 2>/dev/null

    # Ancient scripts and exotic languages
    rm -f "$TTFONTS_DIR/Aegean.otf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Aegyptus.otf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Akkadian.otf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Quivira.otf" 2>/dev/null
    rm -f "$TTFONTS_DIR/ZawgyiOne.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/LateefRegOT.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/TaiHeritagePro.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/kaputaunicode.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/lannaalif-v1-03.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/TaameyDavidCLM-Medium.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/SundaneseUnicode-1.0.5.ttf" 2>/dev/null
    rm -f "$TTFONTS_DIR/SyrCOMEdessa.otf" 2>/dev/null
    rm -f "$TTFONTS_DIR/Uthman.otf" 2>/dev/null

    # Pattern-based removal (using find to avoid glob errors)
    find "$TTFONTS_DIR" -maxdepth 1 -name "XB*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Aboriginal*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Lohit*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Padauk*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Pothana*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Rachana*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Kedage*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Gurung*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "KFGQPC*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Tharlon*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Dhyana*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Garuda*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Norasi*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "damase*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "ind_*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "Noto*" -delete 2>/dev/null
    find "$TTFONTS_DIR" -maxdepth 1 -name "KhmerOS*" -delete 2>/dev/null
fi

# Remove tests, docs, examples from all vendor packages
echo "Removing tests and documentation..."
find "$VENDOR_DIR" -type d \( -name tests -o -name Tests -o -name test \) -exec rm -rf {} + 2>/dev/null
find "$VENDOR_DIR" -type d \( -name docs -o -name doc -o -name examples -o -name samples \) -exec rm -rf {} + 2>/dev/null

echo "Cleanup complete."
