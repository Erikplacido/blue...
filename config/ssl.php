<?php
/**
 * Gerenciador de SSL/HTTPS - Blue Project V2
 * Sistema para configuração e enforço de HTTPS
 */

class SSLManager {
    
    private $config;
    private $logger;
    
    public function __construct() {
        $this->config = EnvironmentConfig::get('ssl', [
            'enabled' => false,
            'force_https' => false,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000,
            'certificate_path' => '/etc/ssl/certs/bluecleaningservices.pem',
            'private_key_path' => '/etc/ssl/private/bluecleaningservices.key',
            'ca_bundle_path' => '/etc/ssl/certs/ca-bundle.crt'
        ]);
        
        if (class_exists('Logger')) {
            $this->logger = Logger::getInstance();
        }
    }
    
    /**
     * Inicializar SSL
     */
    public function initialize() {
        // Aplicar headers de segurança
        $this->applySecurityHeaders();
        
        // Forçar HTTPS se habilitado
        if ($this->config['force_https']) {
            $this->forceHTTPS();
        }
        
        // Verificar certificado SSL
        if ($this->config['enabled']) {
            $this->verifyCertificate();
        }
    }
    
    /**
     * Aplicar headers de segurança
     */
    public function applySecurityHeaders() {
        // HSTS - HTTP Strict Transport Security
        if ($this->config['hsts_enabled'] && $this->isHTTPS()) {
            header('Strict-Transport-Security: max-age=' . $this->config['hsts_max_age'] . '; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = $this->buildCSP();
        if ($csp) {
            header('Content-Security-Policy: ' . $csp);
        }
        
        // X-Frame-Options
        header('X-Frame-Options: DENY');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        if ($this->logger) {
            $this->logger->info('SSL security headers applied');
        }
    }
    
    /**
     * Forçar HTTPS
     */
    public function forceHTTPS() {
        if (!$this->isHTTPS()) {
            $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            
            if ($this->logger) {
                $this->logger->info('Redirecting to HTTPS', ['redirect_url' => $redirectURL]);
            }
            
            header('Location: ' . $redirectURL, true, 301);
            exit;
        }
    }
    
    /**
     * Verificar se é HTTPS
     */
    public function isHTTPS() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
    }
    
    /**
     * Construir Content Security Policy
     */
    private function buildCSP() {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.google.com https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "connect-src 'self' https://api.stripe.com",
            "frame-src https://js.stripe.com https://hooks.stripe.com",
            "object-src 'none'",
            "base-uri 'self'"
        ];
        
        return implode('; ', $directives);
    }
    
    /**
     * Verificar certificado SSL
     */
    public function verifyCertificate() {
        if (!file_exists($this->config['certificate_path'])) {
            if ($this->logger) {
                $this->logger->error('SSL certificate not found', [
                    'path' => $this->config['certificate_path']
                ]);
            }
            return false;
        }
        
        $certificate = file_get_contents($this->config['certificate_path']);
        $cert = openssl_x509_parse($certificate);
        
        if (!$cert) {
            if ($this->logger) {
                $this->logger->error('Invalid SSL certificate');
            }
            return false;
        }
        
        // Verificar se o certificado não expirou
        $expiryDate = $cert['validTo_time_t'];
        $now = time();
        $daysUntilExpiry = ($expiryDate - $now) / (24 * 60 * 60);
        
        if ($daysUntilExpiry < 0) {
            if ($this->logger) {
                $this->logger->error('SSL certificate has expired', [
                    'expiry_date' => date('Y-m-d H:i:s', $expiryDate)
                ]);
            }
            return false;
        }
        
        // Alerta se o certificado expira em menos de 30 dias
        if ($daysUntilExpiry < 30) {
            if ($this->logger) {
                $this->logger->warning('SSL certificate expires soon', [
                    'days_until_expiry' => round($daysUntilExpiry),
                    'expiry_date' => date('Y-m-d H:i:s', $expiryDate)
                ]);
            }
        }
        
        if ($this->logger) {
            $this->logger->info('SSL certificate verification completed', [
                'subject' => $cert['subject']['CN'] ?? 'Unknown',
                'issuer' => $cert['issuer']['CN'] ?? 'Unknown',
                'days_until_expiry' => round($daysUntilExpiry)
            ]);
        }
        
        return true;
    }
    
    /**
     * Obter informações do certificado
     */
    public function getCertificateInfo() {
        if (!file_exists($this->config['certificate_path'])) {
            return null;
        }
        
        $certificate = file_get_contents($this->config['certificate_path']);
        $cert = openssl_x509_parse($certificate);
        
        if (!$cert) {
            return null;
        }
        
        return [
            'subject' => $cert['subject'],
            'issuer' => $cert['issuer'],
            'valid_from' => date('Y-m-d H:i:s', $cert['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $cert['validTo_time_t']),
            'serial_number' => $cert['serialNumber'],
            'signature_algorithm' => $cert['signatureTypeSN']
        ];
    }
    
    /**
     * Gerar CSR (Certificate Signing Request)
     */
    public function generateCSR($domains, $organization = 'Blue Cleaning Services') {
        $dn = [
            'countryName' => 'AU',
            'stateOrProvinceName' => 'Victoria',
            'localityName' => 'Melbourne',
            'organizationName' => $organization,
            'organizationalUnitName' => 'IT Department',
            'commonName' => $domains[0],
            'emailAddress' => 'admin@bluecleaningservices.com.au'
        ];
        
        // Configuração da chave privada
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ];
        
        // Gerar chave privada
        $privateKey = openssl_pkey_new($config);
        
        // Gerar CSR
        $csr = openssl_csr_new($dn, $privateKey);
        
        if (!$csr) {
            return false;
        }
        
        // Exportar CSR
        openssl_csr_export($csr, $csrString);
        
        // Exportar chave privada
        openssl_pkey_export($privateKey, $privateKeyString);
        
        return [
            'csr' => $csrString,
            'private_key' => $privateKeyString
        ];
    }
    
    /**
     * Configurar Apache para SSL
     */
    public function generateApacheConfig() {
        $template = <<<'APACHE'
<VirtualHost *:443>
    ServerName bluecleaningservices.com.au
    ServerAlias www.bluecleaningservices.com.au
    DocumentRoot /var/www/html
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile {CERTIFICATE_PATH}
    SSLCertificateKeyFile {PRIVATE_KEY_PATH}
    SSLCertificateChainFile {CA_BUNDLE_PATH}
    
    # SSL Protocol and Cipher Configuration
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256
    SSLHonorCipherOrder on
    SSLCompression off
    
    # HSTS
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    
    # OCSP Stapling
    SSLUseStapling on
    SSLStaplingResponderTimeout 5
    SSLStaplingReturnResponderErrors off
    
    # Security Headers
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/ssl_access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName bluecleaningservices.com.au
    ServerAlias www.bluecleaningservices.com.au
    
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
APACHE;
        
        return str_replace([
            '{CERTIFICATE_PATH}',
            '{PRIVATE_KEY_PATH}',
            '{CA_BUNDLE_PATH}'
        ], [
            $this->config['certificate_path'],
            $this->config['private_key_path'],
            $this->config['ca_bundle_path']
        ], $template);
    }
    
    /**
     * Configurar Nginx para SSL
     */
    public function generateNginxConfig() {
        $template = <<<'NGINX'
server {
    listen 80;
    server_name bluecleaningservices.com.au www.bluecleaningservices.com.au;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name bluecleaningservices.com.au www.bluecleaningservices.com.au;
    
    root /var/www/html;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate {CERTIFICATE_PATH};
    ssl_certificate_key {PRIVATE_KEY_PATH};
    ssl_trusted_certificate {CA_BUNDLE_PATH};
    
    # SSL Protocol and Cipher Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    
    # SSL Session
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;
    
    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
    }
    
    # Static Files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ \.(log|env)$ {
        deny all;
    }
}
NGINX;
        
        return str_replace([
            '{CERTIFICATE_PATH}',
            '{PRIVATE_KEY_PATH}',
            '{CA_BUNDLE_PATH}'
        ], [
            $this->config['certificate_path'],
            $this->config['private_key_path'],
            $this->config['ca_bundle_path']
        ], $template);
    }
    
    /**
     * Testar configuração SSL
     */
    public function testSSLConfiguration() {
        $results = [];
        
        // Teste 1: Verificar se HTTPS está funcionando
        $url = 'https://' . $_SERVER['HTTP_HOST'];
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        $results['https_working'] = $response !== false;
        
        // Teste 2: Verificar certificado SSL
        $results['certificate_valid'] = $this->verifyCertificate();
        
        // Teste 3: Verificar headers de segurança
        $headers = get_headers($url, 1);
        $results['hsts_header'] = isset($headers['Strict-Transport-Security']);
        $results['xframe_header'] = isset($headers['X-Frame-Options']);
        
        // Teste 4: Verificar redirecionamento HTTP para HTTPS
        $httpUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $httpHeaders = @get_headers($httpUrl);
        $results['http_redirect'] = $httpHeaders && strpos($httpHeaders[0], '301') !== false;
        
        return $results;
    }
}

// Exemplo de uso
if (basename(__FILE__) === 'ssl.php') {
    $ssl = new SSLManager();
    $ssl->initialize();
    
    // Exibir informações do certificado se solicitado
    if (isset($_GET['cert_info'])) {
        header('Content-Type: application/json');
        echo json_encode($ssl->getCertificateInfo(), JSON_PRETTY_PRINT);
        exit;
    }
    
    // Testar configuração SSL se solicitado
    if (isset($_GET['test'])) {
        header('Content-Type: application/json');
        echo json_encode($ssl->testSSLConfiguration(), JSON_PRETTY_PRINT);
        exit;
    }
}

?>
