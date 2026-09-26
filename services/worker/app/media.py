from __future__ import annotations
import json
import subprocess
from pathlib import Path

def duration_ms(path: Path) -> int | None:
    try:
        result = subprocess.run([
            'ffprobe','-v','quiet','-print_format','json','-show_format',str(path)
        ], check=True, capture_output=True, text=True, timeout=30)
        seconds = float(json.loads(result.stdout).get('format', {}).get('duration', 0))
        return int(seconds * 1000) if seconds > 0 else None
    except (FileNotFoundError, OSError, subprocess.SubprocessError, ValueError, json.JSONDecodeError):
        return None
