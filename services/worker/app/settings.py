from pydantic_settings import BaseSettings, SettingsConfigDict

class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file='.env', extra='ignore')
    port: int = 8100
    transcription_backend: str = 'mock'
    openai_api_key: str | None = None
    openai_transcription_model: str = 'gpt-4o-transcribe-diarize'
    internal_shared_secret: str = 'change-this-internal-secret'
    max_download_bytes: int = 600 * 1024 * 1024

settings = Settings()
