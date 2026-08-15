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

## 2026-08-09 - [CX] 第三轮 1.9.6 候选、矩阵与安全升级

- [CX] 第三轮实现提交 `82c61ea67a84c5470ae28aca0fea6d91a0f058ed`：版本头、常量、Stable tag 与 changelog 同步为 1.9.6；主插件头补最低 WordPress 5.4；打包脚本增加三重版本门禁、changelog、权限、符号链接和可复现时间戳检查。
- [CX] WordPress 5.4/PHP 7.4.33 与 WordPress 7.0.3/PHP 8.3.33 的单站/多站真实矩阵均 exit 0；均验证候选 1.9.6、Cravatar 路由、缓存隔离、HTTP 和插件阶段 debug 0。失败与协调中止样本保留在 `tests/compat-matrix/results/`。
- [CX] 旧 v1.9.5 官方源 parse fatal 为 exit 255；新增不加载旧插件的安全替换入口。最终 `final-fast-1786278072` 单站/网络多站 1.9.5→1.9.6 均 PASS，DB/代码备份和 option/site_option/缓存/sentinel 保留；历史权限、MariaDB 与 timeout 124 FAIL 未隐藏。
- [CX 2026-08-11 approved] 营销短代码在 1.9.6 中全部保留且行为不变；下一主版本拆 commenters/users/random add-on，author 留核心，并保留 option、正文、兼容迁移和回滚。
- [CX] 本地 release PHPCS 0/0；全量 PHPCS 77/18 FAIL；PHPStan level 5 PASS，level 6 初始 186 FAIL；PHPUnit 12/26 PASS。
- [CX] PR #2 旧 HEAD 的 gitleaks/security-scan/wp-plugin-ci 三个 job 全部因 fork PR 审批门禁 BLOCKED、零执行样本。最终 HEAD 推送后需重查，只有具备 FeiCode Actions 审批权限者可放行。
- [CX 2026-08-11 resolved] 用户已批准 1.9.6、PHP 7.4、WordPress.org 唯一公开自动更新通道、FeiCode 代码真源/release 资产，以及下一主版本拆 marketing add-on 并保留迁移/回滚。仍禁止合并、发布、部署。
- [CX] 第三轮 1.9.6 评审包（待发布收口轮次取代）：`dist/wpavatar-1.9.6.zip`，SHA-256 `d9073e499cf2927b1ec6728bd016053f874d50364f89f1fcf6775d96e5ad29a2`；两次可复现构建一致，21 文件逐项 hash/权限/包内 lint/unzip 均 PASS；Plugin Check 0 errors/14 warnings、exit 0。仅供评审，未发布。


## 2026-08-11 - [CX] 发布收口第一批

- [CX] 已按批准决策统一为候选 1.9.6、最低 WordPress 5.4、最低 PHP 7.4；WordPress.org 是唯一自动更新通道，FeiCode 只作代码真源和人工 release 资产保存位置。运行源码无 `Update URI` 或自定义 updater hook。
- [CX] 营销/社区列表短代码在 1.9.6 保留且行为不变；下一主版本拆 commenters/users/random add-on，author 留核心；迁移与回滚不删 option、正文或用户数据。决策文档：`docs/marketing-shortcode-migration.md`。
- [CX] 收口实现提交 `PR #2 发布收口最终 HEAD`。以 `SOURCE_DATE_EPOCH=1786418697` 双构建一致的候选 `dist/wpavatar-1.9.6.zip` SHA-256 为 `c87124add4aa580ed641290fafb0fb0dbb1b9e2b00765495ae4f19dfaccd60b1`；包内 lint、unzip、逐文件 hash 和 0755/0644 权限均 PASS。
- [CX] PHPUnit 12/26、PHPStan 5、release PHPCS 0/0、Gitleaks、Plugin Check process、E2E、WP5.4/PHP7.4 单/多站、WP7.0.3/PHP8.3 单/多站、1.9.5→1.9.6 安全替换均 exit 0。Plugin Check 保留 0 errors/14 warnings；全量 PHPCS 77/18 与 PHPStan level 6 初始 186 仍为 FAIL。各次 harness/infra FAIL 和 E2E 的已知旧 runtime SKIP 未删除，详见 `docs/audit-2026-08-09.md`。
- [CX] FeiCode 为 P0、`write_policy=board_required`。PR #2 更新及精确 HEAD 的 fork PR CI 审批必须由 devops 建立/审阅 Board control/change 任务；说明见 `docs/devops-ci-approval.md`。Codex 未自行批准 CI、合并、创建 release、发布或部署。
