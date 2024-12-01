# PHP Audio Processing API

This project implements a PHP-based API that allows users to:

1. Upload an audio file.
2. Transcribe the audio to text.
3. Translate the transcription to a target language.
4. Convert the translated text to speech.

The project integrates several external APIs to perform these tasks:


- **Groq API**: For translation.
- **ElevenLabs API**: For text-to-speech conversion.

## Requirements

- **PHP 7.4+**: For running the script.
- **cURL extension for PHP**: Required for making HTTP requests.
- **API Keys**: You need valid API keys for the following services:
  
  - Groq API
  - ElevenLabs API

## Installation

1. **Clone the Repository**  
   Clone the project repository to your local machine:

   ```bash
   git clone https://github.com/your-username/php-audio-processing-api.git
   cd php-audio-processing-api
