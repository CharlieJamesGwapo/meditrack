<?php
/**
 * QR Code Generator - Works WITHOUT Composer!
 * Uses Google Charts API - No external dependencies needed
 */

class QRCodeGenerator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get the server base URL from settings or auto-detect
     */
    private function getServerBaseUrl() {
        if (defined('APP_URL')) {
            return APP_URL;
        }
        $ip = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
        if ($ip === '::1') $ip = '127.0.0.1';
        $port = $_SERVER['SERVER_PORT'] ?? '80';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $portSuffix = ($port === '80' || $port === '443') ? '' : ':' . $port;
        return "{$scheme}://{$ip}{$portSuffix}/meditrack";
    }

    public function generateQRCode($appointment_id) {
        try {
            // Create QR payload
            $payload = [
                'appointment_id' => $appointment_id,
                'timestamp' => time(),
                'random' => bin2hex(random_bytes(16))
            ];

            $payloadJson = json_encode($payload);
            $secretKey = defined('SECRET_KEY') ? SECRET_KEY : 'meditrack_secret_2024';
            $signature = hash_hmac('sha256', $payloadJson, $secretKey);
            $tokenHash = hash('sha256', $payloadJson . $signature);

            // Calculate expiry
            $expiryHours = defined('QR_EXPIRY_HOURS') ? QR_EXPIRY_HOURS : 24;
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $expiryHours . ' hours'));

            // Store in database (update if exists)
            $query = "INSERT INTO qr_tokens (appointment_id, qr_payload, signature, token_hash, expires_at)
                      VALUES (:appointment_id, :qr_payload, :signature, :token_hash, :expires_at)
                      ON DUPLICATE KEY UPDATE
                      qr_payload = VALUES(qr_payload),
                      signature = VALUES(signature),
                      token_hash = VALUES(token_hash),
                      expires_at = VALUES(expires_at),
                      is_used = 0";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':qr_payload' => $payloadJson,
                ':signature' => $signature,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt
            ]);

            // Build the QR content: a URL that works on the local network
            $baseUrl = $this->getServerBaseUrl();
            $qrContent = "{$baseUrl}/pages/qr-checkin.html?token={$tokenHash}";

            // Generate QR code image
            $qrSize = defined('QR_SIZE') ? QR_SIZE : 300;
            $qrImageData = $this->generateQRImage($qrContent, $qrSize);

            return [
                'token_hash' => $tokenHash,
                'qr_image' => $qrImageData,
                'qr_url' => $qrContent,
                'expires_at' => $expiresAt,
                'server_url' => $baseUrl
            ];

        } catch (Exception $e) {
            error_log("QR Generation Error: " . $e->getMessage());
            throw new Exception("Failed to generate QR code: " . $e->getMessage());
        }
    }

    /**
     * Generate QR image - returns base64 data URI or falls back to direct URL
     * Designed to NEVER crash, even if the server blocks all outbound HTTP requests.
     */
    private function generateQRImage($data, $size = 300) {
        $encodedData = urlencode($data);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}&format=png";

        // Try cURL first
        if (function_exists('curl_init')) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $qrUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'IM-OPD/1.0'
                ]);
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if (!$error && $httpCode === 200 && $imageData && strlen($imageData) > 100) {
                    return 'data:image/png;base64,' . base64_encode($imageData);
                }
            } catch (Exception $e) {
                error_log("QR cURL error: " . $e->getMessage());
            }
        }

        // Try file_get_contents
        if (ini_get('allow_url_fopen')) {
            try {
                $context = stream_context_create([
                    'http' => ['timeout' => 8, 'ignore_errors' => true],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                ]);
                $imageData = @file_get_contents($qrUrl, false, $context);
                if ($imageData && strlen($imageData) > 100) {
                    return 'data:image/png;base64,' . base64_encode($imageData);
                }
            } catch (Exception $e) {
                error_log("QR file_get_contents error: " . $e->getMessage());
            }
        }

        // Fallback 2: Try Google Charts API
        $googleUrl = "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$encodedData}&choe=UTF-8";
        if (function_exists('curl_init')) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $googleUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                $imageData = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200 && $imageData && strlen($imageData) > 100) {
                    return 'data:image/png;base64,' . base64_encode($imageData);
                }
            } catch (Exception $e) {
                error_log("QR Google Charts error: " . $e->getMessage());
            }
        }

        // Final fallback: return the URL directly - browser will load it as img src
        return $qrUrl;
    }
    
    public function validateQRCode($tokenHash, $userId = null) {
        $query = "SELECT qt.*, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status
                  FROM qr_tokens qt
                  JOIN appointments a ON qt.appointment_id = a.id
                  WHERE qt.token_hash = :token_hash 
                  AND qt.is_used = 0 
                  AND qt.expires_at > NOW()";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':token_hash' => $tokenHash]);
        
        if ($stmt->rowCount() === 0) {
            return ['valid' => false, 'message' => 'Invalid or expired QR code'];
        }
        
        $token = $stmt->fetch();
        
        // Verify signature
        $secretKey = defined('SECRET_KEY') ? SECRET_KEY : 'meditrack_secret_2024';
        $signature = hash_hmac('sha256', $token['qr_payload'], $secretKey);
        if (!hash_equals($signature, $token['signature'])) {
            return ['valid' => false, 'message' => 'QR code signature invalid'];
        }
        
        // Mark as used
        $updateQuery = "UPDATE qr_tokens SET is_used = 1, used_at = NOW(), used_by = :used_by WHERE id = :id";
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->execute([':used_by' => $userId, ':id' => $token['id']]);
        
        return [
            'valid' => true,
            'appointment_id' => $token['appointment_id'],
            'patient_id' => $token['patient_id'],
            'doctor_id' => $token['doctor_id'],
            'appointment_date' => $token['appointment_date'],
            'appointment_time' => $token['appointment_time'],
            'status' => $token['status']
        ];
    }
}
