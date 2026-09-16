<?php
namespace App\Helpers;
use App\Core\DB;

class CustomerAuth
{
    public const SESSION_KEY = 'vp_customer';
    public static function ensureSchema(DB $db): void {
        $db->query("CREATE TABLE IF NOT EXISTS vp_customers (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,email VARCHAR(190) NOT NULL,full_name VARCHAR(150) NOT NULL,avatar_url VARCHAR(500) NULL,phone VARCHAR(32) NULL,status ENUM('active','blocked') NOT NULL DEFAULT 'active',last_login_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY vp_customers_email_unique(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->query("CREATE TABLE IF NOT EXISTS vp_customer_identities (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,customer_id BIGINT UNSIGNED NOT NULL,provider VARCHAR(40) NOT NULL,provider_subject VARCHAR(255) NOT NULL,provider_email VARCHAR(190) NULL,email_verified_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY vp_customer_identity_provider_subject_unique(provider,provider_subject),KEY vp_customer_identities_customer_index(customer_id),CONSTRAINT vp_customer_identities_customer_fk FOREIGN KEY(customer_id) REFERENCES vp_customers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public static function login(array $customer): void { session_regenerate_id(true); $_SESSION[self::SESSION_KEY]=['id'=>(int)$customer['id'],'email'=>(string)$customer['email'],'name'=>(string)$customer['full_name'],'avatarUrl'=>(string)($customer['avatar_url']??'')]; }
    public static function logout(): void { unset($_SESSION[self::SESSION_KEY]); session_regenerate_id(true); }
    public static function user(): ?array { $u=$_SESSION[self::SESSION_KEY]??null; return is_array($u)?$u:null; }
    public static function public(array $c): array { return ['id'=>(int)$c['id'],'email'=>(string)$c['email'],'name'=>(string)$c['full_name'],'avatarUrl'=>$c['avatar_url']??null]; }
}
