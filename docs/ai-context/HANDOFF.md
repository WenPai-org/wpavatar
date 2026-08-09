# WPAvatar 交接

## 2026-08-09 - [CX] 全面审计与安全整治

- 代码真源：WenPai VM `/home/parallels/Projects/wpavatar-feicode`，远端 `ssh://git@feicode.com:2222/WenPai-org/wpavatar.git`。
- 隔离分支：`codex/wpavatar-audit-20260809`；隔离工作树：`/home/parallels/Projects/wpavatar-codex-audit`。
- [CX] 已修复多站点语法 fatal、运行期写插件目录、头像缓存 SSRF/任意缓存路径、设置缺失校验、头像与用户名输出转义、公开短代码无界参数和新站点废弃钩子。
- [CX] 已补 PHPUnit 安全回归、WordPress 7.0.3 单站/多站运行验证、Plugin Check、PHPCS、Gitleaks和 allowlist 发布包检查。完整命令、结果和未完成项见 `docs/audit-2026-08-09.md`。
- [CX] 未部署、未发布、未合并默认分支；未覆盖共享仓库的 `includes/multisite.php` 与 `.forgejo` 未提交 WIP。
- [CX] FeiCode 直接推送分支被 pre-receive hook 拒绝；本地提交已保留，未擅自通过 `refs/for` 创建 PR。
- [CX] 产品决策仍未完成：WordPress.org/FeiCode 更新通道、营销短代码拆分/弃用周期、卸载数据策略、500 站点批处理、异步缓存。
