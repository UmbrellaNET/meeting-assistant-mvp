from __future__ import annotations
import re
from pathlib import Path
from .schemas import Segment

SPEAKER_PATTERN = re.compile(r'^\s*([^:]{1,80}):\s*(.+)$', re.DOTALL)
TIMING_PATTERN = re.compile(r'(?P<start>\d{2}:\d{2}(?::\d{2})?[\.,]\d{3})\s+-->\s+(?P<end>\d{2}:\d{2}(?::\d{2})?[\.,]\d{3})')

def timestamp_to_ms(value: str) -> int:
    parts = value.replace(',', '.').split(':')
    if len(parts) == 2:
        hours = 0
        minutes, seconds = parts
    else:
        hours, minutes, seconds = parts
    return int((int(hours) * 3600 + int(minutes) * 60 + float(seconds)) * 1000)

def split_speaker(text: str, fallback: str = 'Speaker 1') -> tuple[str, str]:
    clean = ' '.join(text.strip().split())
    match = SPEAKER_PATTERN.match(clean)
    if match:
        return match.group(1).strip(), match.group(2).strip()
    return fallback, clean

def parse_vtt(path: Path) -> list[Segment]:
    lines = path.read_text(encoding='utf-8-sig', errors='replace').splitlines()
    segments: list[Segment] = []
    index = 0
    while index < len(lines):
        timing = TIMING_PATTERN.search(lines[index])
        if not timing:
            index += 1
            continue
        text_lines: list[str] = []
        index += 1
        while index < len(lines) and lines[index].strip():
            text_lines.append(lines[index].strip())
            index += 1
        speaker, text = split_speaker(' '.join(text_lines))
        if text:
            segments.append(Segment(
                start_ms=timestamp_to_ms(timing.group('start')),
                end_ms=timestamp_to_ms(timing.group('end')),
                speaker=speaker,
                text=text,
                confidence=None,
                source_reference=f'vtt:{len(segments) + 1}',
            ))
        index += 1
    return segments

def parse_plain_text(path: Path) -> list[Segment]:
    lines = [line.strip() for line in path.read_text(encoding='utf-8', errors='replace').splitlines() if line.strip()]
    segments: list[Segment] = []
    cursor = 0
    for index, line in enumerate(lines, start=1):
        speaker, text = split_speaker(line)
        duration = max(2500, min(15000, len(text.split()) * 450))
        segments.append(Segment(start_ms=cursor, end_ms=cursor + duration, speaker=speaker, text=text, source_reference=f'text:{index}'))
        cursor += duration + 250
    return segments
