<?php

declare(strict_types=1);

/**
 * AJAX API Endpoint: Research Type Predictor
 * TAU-TeSI Portal ML Integration
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Phpml\ModelManager;
use Phpml\Tokenization\WordTokenizer;

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Helper to send JSON error response
function sendError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}

// Get the title from POST or GET
$title = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if JSON request
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $input = json_decode(file_get_contents('php://input'), true);
        $title = $input['title'] ?? '';
    } else {
        $title = $_POST['title'] ?? '';
    }
} else {
    $title = $_GET['title'] ?? '';
}

// Basic input validation
$title = trim($title);
if (empty($title)) {
    sendError('Title is empty.');
}

if (mb_strlen($title) < 5) {
    sendError('Title is too short.');
}

// Define paths
$modelsDir = __DIR__ . '/../models/';
$modelPath = $modelsDir . 'research_type_naivebayes_latest.phpml';
$metadataPath = $modelsDir . 'research_type_naivebayes_latest.json';

if (!file_exists($modelPath) || !file_exists($metadataPath)) {
    sendError('Trained machine learning model is not available.', 500);
}

try {
    // Load Model (static container singleton style)
    static $model = null;
    $model ??= (new ModelManager())->restoreFromFile($modelPath);
    
    // Load Metadata JSON
    $metadata = json_decode((string) file_get_contents($metadataPath), true);
    if ($metadata === null) {
        throw new RuntimeException('Failed to parse model metadata JSON.');
    }
    
    // Perform Prediction
    $predictions = $model->predict([$title]);
    $prediction = (string) $predictions[0];
    
    // Extract tokens from input title to find word indicators
    $tokenizer = new WordTokenizer();
    $tokens = $tokenizer->tokenize(mb_strtolower($title));
    
    // Match tokens against indicators in metadata
    $matchedIndicators = [];
    $indicatorsList = $metadata['indicators'] ?? [];
    
    foreach ($tokens as $token) {
        $found = false;
        foreach ($indicatorsList as $cat => $words) {
            foreach ($words as $item) {
                if ($item['word'] === $token) {
                    $matchedIndicators[] = [
                        'word' => $token,
                        'category' => $cat,
                        'weight' => $item['weight']
                    ];
                    $found = true;
                    break;
                }
            }
            if ($found) {
                break;
            }
        }
    }
    
    // Sort matched indicators by weight descending
    usort($matchedIndicators, function (array $a, array $b): int {
        return $b['weight'] <=> $a['weight'];
    });
    
    // Return successful prediction
    echo json_encode([
        'status' => 'success',
        'prediction' => $prediction,
        'model_version' => $metadata['version'] ?? 'unknown',
        'model_accuracy' => $metadata['accuracy'] ?? 0.0,
        'indicators' => $matchedIndicators
    ]);
    
} catch (Throwable $e) {
    error_log('[predict-research-type] Error: ' . $e->getMessage());
    sendError('Failed to perform AI classification: ' . $e->getMessage(), 500);
}
