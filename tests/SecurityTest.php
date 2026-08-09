<?php
use PHPUnit\Framework\TestCase;
final class SecurityTest extends TestCase {
 protected function setUp(): void { $GLOBALS['wpavatar_test_options']=array(); $GLOBALS['wpavatar_settings_errors']=array(); $GLOBALS['wpavatar_test_user']=false; }
 public function testCustomCdnValidation(): void {
  self::assertSame('cdn.example.com',wpavatar_sanitize_custom_cdn('CDN.Example.com.'));
  self::assertSame('',wpavatar_sanitize_custom_cdn('https://cdn.example.com/path'));
  self::assertSame('',wpavatar_sanitize_custom_cdn('127.0.0.1'));
  self::assertSame('',wpavatar_sanitize_custom_cdn('cdn.example.com:8443'));
 }
 public function testAvatarUrlAllowlist(): void {
  $GLOBALS['wpavatar_test_options']['wpavatar_cdn_type']='custom'; $GLOBALS['wpavatar_test_options']['wpavatar_custom_cdn']='cdn.example.com';
  self::assertTrue(wpavatar_is_allowed_avatar_url('https://cdn.example.com/avatar/abc'));
  self::assertFalse(wpavatar_is_allowed_avatar_url('http://cdn.example.com/avatar/abc'));
  self::assertFalse(wpavatar_is_allowed_avatar_url('https://cdn.example.com.evil.test/avatar/abc'));
  self::assertFalse(wpavatar_is_allowed_avatar_url('https://127.0.0.1/avatar/abc'));
 }
 public function testExactHostReplacement(): void {
  $url='https://evil.test/avatar/abc?next=www.gravatar.com';
  self::assertSame($url,\WPAvatar\Cravatar::replace_avatar_url($url));
  self::assertSame('https://cravatar.com/avatar/abc?s=96',\WPAvatar\Cravatar::replace_avatar_url('https://www.gravatar.com/avatar/abc?s=96'));
 }
 public function testHashUrlRebuild(): void {
  $hash=str_repeat('a',64);
  self::assertSame('https://cravatar.com/avatar/'.$hash.'?s=80&d=mp',\WPAvatar\Cravatar::get_avatar_url('https://secure.gravatar.com/avatar/'.$hash.'?s=80&d=mp','user@example.com'));
 }
 public function testCachePathConfinement(): void {
  self::assertSame(trailingslashit(WPAVATAR_CACHE_DIR),wpavatar_sanitize_cache_path('/etc/wpavatar'));
  self::assertSame(trailingslashit(WPAVATAR_CACHE_DIR),wpavatar_sanitize_cache_path('../escape'));
  self::assertSame(trailingslashit(WP_CONTENT_DIR.'/custom-cache'),wpavatar_sanitize_cache_path(WP_CONTENT_DIR.'/custom-cache'));
 }
 public function testCacheFilenameValidation(): void {
  self::assertSame('',\WPAvatar\Cache::get_cache_path('../escape',96));
  self::assertStringEndsWith(str_repeat('b',64).'-512.jpg',\WPAvatar\Cache::get_cache_path(str_repeat('b',64),99999));
 }
 public function testRemoteCacheRejectsUnapprovedUrl(): void {
  self::assertFalse(\WPAvatar\Cache::cache_remote_avatar('https://127.0.0.1/secret',WPAVATAR_CACHE_DIR.'/x.jpg'));
 }
 public function testCachedAvatarUsesRealSrcInsteadOfOnerrorFallback(): void {
  $email='cache@example.com';
  $path=\WPAvatar\Cache::get_cache_path(hash('sha256',$email),51);
  file_put_contents($path,'cached');
  $avatar='<img onerror="this.src=\'https://fallback.example/avatar.png\'" src="https://cravatar.com/avatar/abc?s=51&amp;d=mm">';
  $result=\WPAvatar\Cache::serve_cached_avatar($avatar,$email,51,'','');
  self::assertStringContainsString('onerror="this.src=\'https://fallback.example/avatar.png\'"',$result);
  self::assertStringContainsString('src="https://example.test/wp-content/uploads/cravatar/'.hash('sha256',$email).'-51.jpg"',$result);
  unlink($path);
 }
 public function testCachedAvatarRejectsLookalikeHost(): void {
  $avatar='<img src="https://cravatar.com.evil.test/avatar/abc">';
  self::assertSame($avatar,\WPAvatar\Cache::serve_cached_avatar($avatar,'user@example.com',96,'',''));
 }
 public function testOutputEscaping(): void {
  $GLOBALS['wpavatar_test_options']['wpavatar_seo_alt']='%s avatar'; $GLOBALS['wpavatar_test_options']['wpavatar_fallback_mode']=false;
  $GLOBALS['wpavatar_test_user']=(object)array('ID'=>7,'display_name'=>'x" onerror="alert(1)');
  $avatar=\WPAvatar\Cravatar::add_seo_alt('<img src="x" alt="old">',7,96,'','');
  self::assertStringContainsString('&quot;',$avatar);
  self::assertSame('<b>x&quot; onerror=&quot;alert(1)</b>',\WPAvatar\Shortcode::render_username(array('user_id'=>7,'before'=>'<b>','after'=>'</b>')));
 }
}
