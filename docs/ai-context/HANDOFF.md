# WPAvatar 交接

## 2026-08-09 - [CX] 全面审计与安全整治

- 代码真源：WenPai VM `/home/parallels/Projects/wpavatar-feicode`，远端 `ssh://git@feicode.com:2222/WenPai-org/wpavatar.git`。
- 隔离分支：`codex/wpavatar-audit-20260809`；隔离工作树：`/home/parallels/Projects/wpavatar-codex-audit`。
- [CX] 已修复多站点语法 fatal、运行期写插件目录、头像缓存 SSRF/任意缓存路径、设置缺失校验、头像与用户名输出转义、公开短代码无界参数和新站点废弃钩子。
- [CX] 已补 PHPUnit 安全回归、WordPress 7.0.3 单站/多站运行验证、Plugin Check、PHPCS、Gitleaks和 allowlist 发布包检查。完整命令、结果和未完成项见 `docs/audit-2026-08-09.md`。
- [CX] 未部署、未发布、未合并默认分支；未覆盖共享仓库的 `includes/multisite.php` 与 `.forgejo` 未提交 WIP。
- [CX] FeiCode 直接推送分支被 pre-receive hook 拒绝；本地提交已保留，未擅自通过 `refs/for` 创建 PR。
- [CX] 产品决策仍未完成：WordPress.org/FeiCode 更新通道、营销短代码拆分/弃用周期、卸载数据策略、500 站点批处理、异步缓存。

## 2026-08-09 - [CX] 第二轮门禁、E2E 与 AGit 评审

- [CX] first-party release-risk PHPCS 已为 0 errors/0 warnings；全量 PHPCS 仍为 77 errors/18 warnings（格式/结构债 FAIL）。最终发布包 Plugin Check 为 0 errors/14 warnings，14 项均保留分类，未把 warning 冒充零发现。
- [CX] 新增 Composer/PHPStan/WP stubs 门禁；level 5 覆盖六个关键文件并扫描兼容层，`composer analyse` exit 0。level 6 初始 186 项类型文档债仍为 FAIL。
- [CX] PHPUnit 12 tests/26 assertions；新增 symlink cache-path escape 和营销评论者分页回归。Playwright/WordPress 7.0.3 独立环境 settings/avatar route/cache purge 1 test PASS，debug log 0 行。
- [CX] 1.9.5 配置 fixture→候选版保留及安全归一化 PASS；旧 `origin/main` runtime 因 multisite 语法错误 lint exit 255，真实 runtime upgrade 标为 SKIP。
- [CX] 营销 shortcode 本轮保留。建议下个小版本采用“新安装默认关闭、旧站按 legacy option 迁移为启用”，再把 commenters/users/random 三个 shortcode 拆为 add-on；author shortcode 留核心，不删选项、不改文章。
- [CX] 最终 allowlist 包 `dist/wpavatar-1.9.5.zip` SHA-256 `d6fcfb96b24056fc5c103fc291d9f1b763ba1d545ef658ee54fadaa2a5d8dd0a`。竞态损坏包已隔离并明确禁止使用。
- [CX] FeiCode AGit dry-run `HEAD:refs/for/main/wpavatar-audit-r2` exit 0 且未创建 PR。正式 PR、最终提交和 URL 见本节后续记录；仍禁止合并、发布和部署。
- [CX] 实现提交 `983f4d922d0c783243f4ed34d4014d0b5210a9f4` 已通过 AGit 创建 FeiCode PR #2：`https://feicode.com/WenPai-org/wpavatar/pulls/2`；PR 保持 open，未合并、未部署、未发布。
