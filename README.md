This project implements a PHP-based API that allows you to upload an audio file, transcribe it, translate the transcription to a target language, and convert the translated text to speech. It uses several external APIs:

Whisper API (for transcription)
Groq API (for translation)
ElevenLabs API (for text-to-speech conversion)
Requirements
PHP 7.4+ (for running the script).
cURL extension for PHP.
API keys for the following services:
Whisper API (for transcription).
Groq API (for translation).
ElevenLabs API (for text-to-speech conversion).
Installation
Clone the repository
Configure environment variables:

Set up your API keys by defining them in your environment or .env file.

export GROQ_API_KEY="your-groq-api-key"
export ELEVENLABS_API_KEY="your-elevenlabs-api-key"
