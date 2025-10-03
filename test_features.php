<?php
// Test file to verify SQL features are loading correctly
require_once 'config/sql_features.php';

echo "<h1>SQL Features Test</h1>";

echo "<h2>Raw Features Structure:</h2>";
$raw = SQLFeatureTracker::getAllFeatures();
echo "<pre>" . print_r(array_keys($raw), true) . "</pre>";

echo "<h2>Flattened Features (first 3):</h2>";
$flattened = SQLFeatureTracker::getFlattenedFeatures();
echo "<pre>" . print_r(array_slice($flattened, 0, 3), true) . "</pre>";

echo "<h2>Total counts:</h2>";
echo "Categories: " . count($raw) . "<br>";
echo "Features: " . count($flattened) . "<br>";

// Test search
echo "<h2>Search test for 'SELECT':</h2>";
$searchResults = SQLFeatureTracker::searchFeatures('SELECT');
echo "<pre>" . print_r($searchResults, true) . "</pre>";
?>