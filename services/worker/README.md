# Python AI Worker

The worker is stateless. Laravel sends a signed artifact URL and receives normalized timestamped segments.

Supported inputs in the starter:

- `.vtt`: parsed with real source timestamps and speaker prefixes
- `.txt` / `.md`: converted into estimated timestamp segments
- audio/video with `TRANSCRIPTION_BACKEND=mock`: deterministic development response
- audio/video with `TRANSCRIPTION_BACKEND=openai`: diarized transcription with timestamped speaker segments

A production build should add dedicated diarization, language detection, redaction, retry-aware provider clients and media preprocessing profiles.
