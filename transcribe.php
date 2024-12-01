<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow cross-origin requests (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only handle POST requests to /transcribe
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Include necessary headers for JSON response
header("Content-Type: application/json");

// Retrieve API key from environment variable for security
$GROQ_API_KEY = getenv('GROQ_API_KEY') ?: '';
$ELEVENLABS_API_KEY = getenv('ELEVENLABS_API_KEY') ?: ''; // Replace with your actual API key

// Check if 'audio' file is present in the request
if (!isset($_FILES['audio'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "No audio file provided"]);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];
$sourceLanguage = isset($_POST['source_language']) ? $_POST['source_language'] : 'en';
$targetLanguage = isset($_POST['target_language']) ? $_POST['target_language'] : 'it';

// Mapping for language codes
$languageCodeMap = [
    'it-IT' => 'it',
    'en-US' => 'en',
    'zh-CN' => 'zh',
    'fr-FR' => 'fr',
    'de-DE' => 'de',
    'es-ES' => 'es',
    'ja-JP' => 'ja',
    'ko-KR' => 'ko',
    'ru-RU' => 'ru',
    'pt-PT' => 'pt'
];

// Convert source language code
$whisperSourceLanguage = isset($languageCodeMap[$sourceLanguage]) ? $languageCodeMap[$sourceLanguage] : $sourceLanguage;

// Function to make POST request using cURL
function makePostRequest($url, $headers, $fields, $isJson = false) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    if ($isJson) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $server_output = curl_exec($ch);
    
    if ($server_output === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: " . $error);
    }
    
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [$server_output, $httpcode];
}

// Function to translate text using Groq's Chat Completions API
function translateText($text, $targetLanguage, $GROQ_API_KEY) {
    $url = "https://api.groq.com/openai/v1/chat/completions";
    $headers = [
        "Authorization: Bearer $GROQ_API_KEY",
        "Content-Type: application/json"
    ];
    
    $languageMap = [
        'it-IT' => 'Italian',
        'en-US' => 'English',
        'zh-CN' => 'Chinese',
        'fr-FR' => 'French',
        'de-DE' => 'German',
        'es-ES' => 'Spanish',
        'ja-JP' => 'Japanese',
        'ko-KR' => 'Korean',
        'ru-RU' => 'Russian',
        'pt-PT' => 'Portuguese',
    ];
    
    $targetLanguageName = isset($languageMap[$targetLanguage]) ? $languageMap[$targetLanguage] : 'English';
    
    $prompt = "
Translate the following text to {$targetLanguageName}. Follow these rules:
1. If the text is already in {$targetLanguageName}, return it as is without any additional information.
2. If there are words or phrases that are not in the source language or {$targetLanguageName}, keep them unchanged in the translation.
3. For names, brand names, or specific terms that should not be translated, keep them in their original form.


Text to translate:
{$text}
";

    $payload = [
        "messages" => [
            ["role" => "system", "content" => "You are a professional translator. Translate the given text according to the provided rules. Only return the translated content without any additional information or explanations. If the translation is hard or it is not possible, please translate it without any additional information."],
            ["role" => "user", "content" => $prompt]
        ],
        "model" => "llama-3.1-70b-versatile",
        "temperature" => 0.2,
        "max_tokens" => 1024
    ];
    
    list($response, $httpcode) = makePostRequest($url, $headers, $payload, true);
    
    if ($httpcode !== 200) {
        throw new Exception("Translation API Error: HTTP $httpcode - $response");
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        return trim($responseData['choices'][0]['message']['content']);
    } else {
        throw new Exception("Unexpected Translation API Response: $response");
    }
}

// Function to convert text to speech using ElevenLabs API
function textToSpeech($text, $ELEVENLABS_API_KEY) {
    $url = "https://api.elevenlabs.io/v1/text-to-speech/21m00Tcm4TlvDq8ikWAM";
    $headers = [
        "xi-api-key: $ELEVENLABS_API_KEY",
        "Content-Type: application/json"
    ];
    
    $fields = [
        "text" => $text,
        "model_id" => "eleven_turbo_v2_5"
    ];
    
    list($response, $httpCode) = makePostRequest($url, $headers, $fields, true);
    
    if ($httpCode !== 200) {
        throw new Exception("Error in text-to-speech conversion: " . $response);
    }
    
    return $response;
}

try {
    // Temporary file handling
    $tempAudioPath = tempnam(sys_get_temp_dir(), 'audio_') . '.webm';
    if (!move_uploaded_file($audioFile, $tempAudioPath)) {
        throw new Exception("Failed to move uploaded file");
    }

    // Get transcription from Whisper API
    $transcriptionUrl = "https://api.groq.com/openai/v1/audio/transcriptions";
    $transcriptionHeaders = [
        "Authorization: Bearer $GROQ_API_KEY"
    ];
    
    $transcriptionFields = [
        'file' => new CURLFile($tempAudioPath),
        'model' => 'whisper-large-v3-turbo',
        'temperature' => '0',
        'response_format' => 'text',
        'language' => $whisperSourceLanguage
    ];
    
    list($transcriptionResponse, $transcriptionHttpCode) = makePostRequest($transcriptionUrl, $transcriptionHeaders, $transcriptionFields);
    
    if ($transcriptionHttpCode !== 200) {
        throw new Exception("Transcription API Error: HTTP $transcriptionHttpCode - $transcriptionResponse");
    }
    
    $transcribedText = trim($transcriptionResponse);
    
    // Get translation from Groq
    $translatedText = translateText($transcribedText, $targetLanguage, $GROQ_API_KEY);
    
    // Convert translated text to speech using ElevenLabs
    $audioContent = textToSpeech($translatedText, $ELEVENLABS_API_KEY);
    
    // Clean up temporary file
    unlink($tempAudioPath);
    
    // Return both translated text and audio content
    echo json_encode([
        "success" => true,
        "transcribed_text" => $transcribedText,
        "translated_text" => $translatedText,
        "audio_content" => base64_encode($audioContent)
    ]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["error" => $e->getMessage()]);
}
?>
