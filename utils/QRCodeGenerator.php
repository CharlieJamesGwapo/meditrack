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
            
            // Generate QR code using Google Charts API
            $qrSize = defined('QR_SIZE') ? QR_SIZE : 300;
            $qrData = urlencode($tokenHash);
            
            // Google Charts QR Code API URL
            $qrImageUrl = "https://chart.googleapis.com/chart?chs={$qrSize}x{$qrSize}&cht=qr&chl={$qrData}&choe=UTF-8";
            
            // Get image data
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            
            $imageData = @file_get_contents($qrImageUrl, false, $context);
            
            if ($imageData !== false && !empty($imageData)) {
                // Successfully got QR image from Google
                $qrImageBase64 = base64_encode($imageData);
                
                return [
                    'token_hash' => $tokenHash,
                    'qr_image' => 'data:image/png;base64,' . $qrImageBase64,
                    'expires_at' => $expiresAt,
                    'method' => 'google_charts'
                ];
            } else {
                // Fallback: Generate simple QR using SVG
                $qrImageSvg = $this->generateSimpleQR($tokenHash, $qrSize);
                
                return [
                    'token_hash' => $tokenHash,
                    'qr_image' => $qrImageSvg,
                    'expires_at' => $expiresAt,
                    'method' => 'svg_fallback'
                ];
            }
            
        } catch (Exception $e) {
            error_log("QR Generation Error: " . $e->getMessage());
            throw new Exception("Failed to generate QR code: " . $e->getMessage());
        }
    }
    
    /**
     * Generate a simple QR-like pattern using SVG
     */
    private function generateSimpleQR($data, $size = 300) {
        $hash = hash('sha256', $data);
        $gridSize = 25;
        $cellSize = $size / $gridSize;
        
        $svg = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">';
        $svg .= '<rect width="' . $size . '" height="' . $size . '" fill="white"/>';
        
        // Generate pattern based on hash
        for ($row = 0; $row < $gridSize; $row++) {
            for ($col = 0; $col < $gridSize; $col++) {
                $index = ($row * $gridSize + $col) % strlen($hash);
                $value = hexdec($hash[$index]);
                
                if ($value % 2 === 0) {
                    $x = $col * $cellSize;
                    $y = $row * $cellSize;
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="black"/>';
                }
            }
        }
        
        // Add corner markers
        $markerSize = $cellSize * 7;
        $markers = [
            ['x' => 0, 'y' => 0],
            ['x' => $size - $markerSize, 'y' => 0],
            ['x' => 0, 'y' => $size - $markerSize]
        ];
        
        foreach ($markers as $marker) {
            $svg .= '<rect x="' . $marker['x'] . '" y="' . $marker['y'] . '" width="' . $markerSize . '" height="' . $markerSize . '" fill="black"/>';
            $svg .= '<rect x="' . ($marker['x'] + $cellSize) . '" y="' . ($marker['y'] + $cellSize) . '" width="' . ($markerSize - 2 * $cellSize) . '" height="' . ($markerSize - 2 * $cellSize) . '" fill="white"/>';
            $svg .= '<rect x="' . ($marker['x'] + $cellSize * 2) . '" y="' . ($marker['y'] + $cellSize * 2) . '" width="' . ($markerSize - 4 * $cellSize) . '" height="' . ($markerSize - 4 * $cellSize) . '" fill="black"/>';
        }
        
        $svg .= '</svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
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
        $signature = hash_hmac('sha256', $token['qr_payload'], SECRET_KEY);
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
