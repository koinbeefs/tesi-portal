<?php

declare(strict_types=1);

/**
 * CLI Training Script for Research Type Classifier
 * TAU-TeSI Portal ML Integration
 */

// Use full path to config and vendor autoload
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Phpml\Classification\NaiveBayes;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Dataset\CsvDataset;
use Phpml\Metric\Accuracy;
use Phpml\ModelManager;
use Phpml\Pipeline;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\Tokenization\WordTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;

// Ensure this script is run via CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run via command line interface.\n";
    exit(1);
}

echo "=== Research Type Classifier Training Started ===\n";

$datasetPath = __DIR__ . '/../data/research_titles_dataset.csv';
$modelsDir = __DIR__ . '/../models/';

if (!file_exists($datasetPath)) {
    echo "Error: Dataset not found at " . $datasetPath . "\n";
    exit(1);
}

if (!is_dir($modelsDir)) {
    mkdir($modelsDir, 0777, true);
}

// 1. Load Dataset
// CsvDataset(filePath, featuresCount, headingRow)
// Since we have title (col 0) and research_type (col 1), featuresCount is 1, headingRow is true
echo "Loading dataset from CSV...\n";
$dataset = new CsvDataset($datasetPath, 1, true);
$samples = $dataset->getSamples(); // Array of [ [0 => "Title text"] ]
$labels = $dataset->getTargets();  // Array of "technical", "social", "social_technical"

// Flatten samples to simple array of strings for Text Classification
$texts = [];
foreach ($samples as $sample) {
    $texts[] = (string) $sample[0];
}

echo sprintf("Loaded %d training samples.\n", count($texts));

// 2. Compute Word Indicators (NLP Feature Engineering)
// Determine which words are highly characteristic of each category
echo "Computing localized word indicators...\n";
$tokenizer = new WordTokenizer();
$wordFreqs = [];
$totalFreqs = [];
$categories = ['technical', 'social', 'social_technical'];

// Initialize frequency counters
foreach ($categories as $cat) {
    $wordFreqs[$cat] = [];
}

for ($i = 0; $i < count($texts); $i++) {
    $text = $texts[$i];
    $label = $labels[$i];
    
    if (!in_array($label, $categories, true)) {
        continue;
    }
    
    $tokens = $tokenizer->tokenize(mb_strtolower($text));
    foreach ($tokens as $token) {
        // Skip short words
        if (mb_strlen($token) < 3) {
            continue;
        }
        
        if (!isset($wordFreqs[$label][$token])) {
            $wordFreqs[$label][$token] = 0;
        }
        $wordFreqs[$label][$token]++;
        
        if (!isset($totalFreqs[$token])) {
            $totalFreqs[$token] = 0;
        }
        $totalFreqs[$token]++;
    }
}

// Score words by specificity and frequency:
// score = (freq_in_category / total_freq) * log(freq_in_category + 1)
$indicators = [];
foreach ($categories as $cat) {
    $scores = [];
    foreach ($wordFreqs[$cat] as $word => $freq) {
        $total = $totalFreqs[$word];
        $specificity = $freq / $total;
        $scores[$word] = $specificity * log($freq + 1);
    }
    
    // Sort words by score descending
    arsort($scores);
    
    // Keep top 40 indicating words for this category
    $indicators[$cat] = array_slice($scores, 0, 40, true);
}

// 3. Split dataset for evaluation (80/20)
echo "Splitting dataset for evaluation...\n";
// php-ml requires samples in 2D array form for StratifiedRandomSplit,
// so we'll wrap strings in 1D array first, then split, then flatten back
$wrappedSamples = array_map(function ($text) {
    return [$text];
}, $texts);

$split = new StratifiedRandomSplit($dataset, 0.2, 42);

$trainSamples2D = $split->getTrainSamples();
$trainLabels = $split->getTrainLabels();
$testSamples2D = $split->getTestSamples();
$testLabels = $split->getTestLabels();

$trainTexts = array_map(function ($s) {
    return (string) $s[0];
}, $trainSamples2D);
$testTexts = array_map(function ($s) {
    return (string) $s[0];
}, $testSamples2D);

// 4. Build and Train Text Pipeline
echo "Training model pipeline (NaiveBayes + WordTokenizer + TF-IDF)...\n";
$pipeline = new Pipeline([
    new TokenCountVectorizer(new WordTokenizer()),
    new TfIdfTransformer(),
], new NaiveBayes());

$pipeline->train($trainTexts, $trainLabels);

// 5. Evaluate Accuracy
echo "Evaluating model accuracy...\n";
$predictions = $pipeline->predict($testTexts);
$accuracy = Accuracy::score($testLabels, $predictions);

echo sprintf("Model evaluation completed.\n");
echo sprintf("Training accuracy achieved: %.2f%%\n", $accuracy * 100);

// 6. Train on full dataset before saving
echo "Retraining pipeline on full dataset for final deployment...\n";
$finalPipeline = new Pipeline([
    new TokenCountVectorizer(new WordTokenizer()),
    new TfIdfTransformer(),
], new NaiveBayes());
$finalPipeline->train($texts, $labels);

// 7. Save Model and Metadata
$version = date('Ymd_His');
$modelFilename = 'research_type_naivebayes_' . $version . '.phpml';
$metadataFilename = 'research_type_naivebayes_' . $version . '.json';

$modelPath = $modelsDir . $modelFilename;
$metadataPath = $modelsDir . $metadataFilename;

$latestModelPath = $modelsDir . 'research_type_naivebayes_latest.phpml';
$latestMetadataPath = $modelsDir . 'research_type_naivebayes_latest.json';

echo "Persisting models and metadata sidecars...\n";
$modelManager = new ModelManager();
$modelManager->saveToFile($finalPipeline, $modelPath);
$modelManager->saveToFile($finalPipeline, $latestModelPath);

// Format word indicators for JSON consumption (just array of words with weights)
$formattedIndicators = [];
foreach ($indicators as $cat => $words) {
    foreach ($words as $word => $weight) {
        $formattedIndicators[$cat][] = [
            'word' => $word,
            'weight' => round($weight, 4)
        ];
    }
}

$metadata = [
    'trained_at' => date('Y-m-d H:i:s'),
    'dataset_size' => count($texts),
    'accuracy' => round($accuracy, 4),
    'algorithm' => 'NaiveBayes_Pipeline',
    'version' => $version,
    'indicators' => $formattedIndicators
];

$jsonContent = json_encode($metadata, JSON_PRETTY_PRINT);
file_put_contents($metadataPath, $jsonContent);
file_put_contents($latestMetadataPath, $jsonContent);

echo sprintf("Saved model to: %s\n", $modelFilename);
echo sprintf("Saved metadata to: %s\n", $metadataFilename);
echo sprintf("Saved copies as latest model and metadata files.\n");
echo "=== Training Process Finished Successfully ===\n";
