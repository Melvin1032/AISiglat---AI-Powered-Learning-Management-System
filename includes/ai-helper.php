<?php
/**
 * Generates a quiz from content using AI (Qwen via DashScope)
 */
function generateQuizFromContent($content, $numQuestions = 5, $apiKey) {
    $url = rtrim(AI_BASE_URL, '/') . "/chat/completions";

    $prompt = "Generate exactly $numQuestions multiple-choice questions from the following educational content.\n" .
              "RULES:\n" .
              "- Output ONLY a valid JSON object. NO explanations, markdown, or extra text.\n" .
              "- JSON format: {\"questions\":[{\"question\":\"...\",\"options\":[\"A. ...\",\"B. ...\",\"C. ...\",\"D. ...\"],\"correct_answer\":\"A\"}]}\n" .
              "- Each question must have 4 options starting with 'A.', 'B.', etc.\n" .
              "- correct_answer must be ONE uppercase letter: A, B, C, or D.\n" .
              "- Do not include any other keys or text.\n\n" .
              "CONTENT:\n$content";

    $data = [
        "model" => AI_MODEL,
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.3,
        "response_format" => ["type" => "json_object"]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 50,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    error_log("AI Call - HTTP: $httpCode, Errno: $errno, Error: $error, Response: " . substr($response, 0, 1000));

    if ($errno) {
        return ['error' => "Network error: $error"];
    }

    if ($httpCode !== 200) {
        return ['error' => "AI API error (HTTP $httpCode)", 'debug' => $response];
    }

    $decoded = json_decode($response, true);
    $aiRawContent = $decoded['choices'][0]['message']['content'] ?? '';

    if (!$aiRawContent) {
        return ['error' => 'Empty AI response', 'raw' => $response];
    }

    $jsonStr = trim($aiRawContent);

    if (preg_match('/```json\s*({.*})\s*```/s', $jsonStr, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/({.*})/s', $jsonStr, $matches)) {
        $jsonStr = $matches[1];
    }

    $quizData = json_decode($jsonStr, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to parse AI JSON', 'raw' => $aiRawContent];
    }

    if (!isset($quizData['questions']) || !is_array($quizData['questions'])) {
        return ['error' => 'Missing "questions" array', 'raw' => $aiRawContent];
    }

    $clean = [];
    foreach ($quizData['questions'] as $q) {
        if (!isset($q['question']) || !isset($q['options']) || !isset($q['correct_answer'])) continue;

        $opts = array_slice((array)$q['options'], 0, 4);
        while (count($opts) < 4) $opts[] = "D. [Placeholder]";

        $clean[] = [
            'question' => trim((string)($q['question'])),
            'options' => array_map(fn($o) => trim((string)$o), $opts),
            'correct_answer' => strtoupper(substr(trim((string)($q['correct_answer'])), 0, 1))
        ];
    }

    if (empty($clean)) {
        return ['error' => 'No valid questions extracted', 'raw' => $aiRawContent];
    }

    return ['quiz' => $clean];
}