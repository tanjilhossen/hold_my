<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value)
    {
        return static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get Guzzle / Http client proxy configuration if proxy is enabled
     */
    public static function getProxyConfig(): array
    {
        $enabled = static::get('proxy_enabled', '1');
        if ($enabled !== '1' && $enabled !== 'true' && $enabled !== true) {
            return [];
        }

        $host = static::get('proxy_host', 'bd.decodo.com');
        $rawPort = trim(static::get('proxy_port', '41001'));
        $user = static::get('proxy_username', 'spua00a572');
        $pass = static::get('proxy_password', 'o3PbblJqa5C6~vzo9M');

        if (empty($host) || empty($rawPort)) {
            return [];
        }

        // Support single port (41001), port range (41001-41010), or comma list (41004,41005,41006)
        if (str_contains($rawPort, '-')) {
            $parts = explode('-', $rawPort);
            $port = rand((int)trim($parts[0]), (int)trim($parts[1]));
        } elseif (str_contains($rawPort, ',')) {
            $ports = array_map('trim', explode(',', $rawPort));
            $port = $ports[array_rand($ports)];
        } else {
            $pNum = (int)$rawPort;
            if ($pNum >= 41001 && $pNum <= 41050) {
                $port = (string)rand(41001, 41020);
            } else {
                $port = (string)$rawPort;
            }
        }

        $auth = (!empty($user) && !empty($pass)) ? "{$user}:{$pass}@" : '';
        $proxyUrl = "http://{$auth}{$host}:{$port}";

        return [
            'proxy' => $proxyUrl,
        ];
    }
}
