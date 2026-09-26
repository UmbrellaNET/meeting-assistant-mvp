# Next.js web application

The starter UI provides:

- Token-based login
- Meeting dashboard
- Meeting registration
- Artifact upload
- Processing-state review
- Timestamped transcript display
- Inline speaker identity correction

The timestamp button emits a `meeting-seek` browser event. Connect that event to an audio/video player once the API exposes a signed playback URL for the relevant recording artifact.
