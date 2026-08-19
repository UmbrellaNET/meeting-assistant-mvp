from pathlib import Path
from app.parsers import parse_plain_text, parse_vtt, timestamp_to_ms

def test_timestamp_to_ms():
    assert timestamp_to_ms('00:01:02.500') == 62500

def test_plain_text_speaker_parsing(tmp_path: Path):
    source = tmp_path / 'meeting.txt'
    source.write_text('Alice: Hello team\nBob: Hello Alice', encoding='utf-8')
    segments = parse_plain_text(source)
    assert segments[0].speaker == 'Alice'
    assert segments[1].text == 'Hello Alice'

def test_vtt_parsing(tmp_path: Path):
    source = tmp_path / 'meeting.vtt'
    source.write_text('WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nAlice: Welcome\n', encoding='utf-8')
    segments = parse_vtt(source)
    assert len(segments) == 1
    assert segments[0].start_ms == 0
    assert segments[0].speaker == 'Alice'
