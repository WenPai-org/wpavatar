# WPAvatar 1.9.6 发布与更新通道决策

> [CX] 用户已批准本决策；本文件只定义评审和人工发布门，不授权代理合并、创建 release、发布 WordPress.org 或部署生产站点。

## 固定元数据

- 候选版本、插件头、`WPAVATAR_VERSION`、Stable tag 和 changelog：`1.9.6`。
- 最低 WordPress：`5.4`。
- 最低 PHP：`7.4`。
- 插件 slug：`wpavatar`。

`scripts/build-release.sh` 和 PR CI 必须同时校验这些值；任一不一致即失败。

## 单一公开更新通道

WordPress.org 是唯一公开自动更新通道。主插件必须保持：

- 不声明 `Update URI`；
- 不注册 `site_transient_update_plugins`、`pre_set_site_transient_update_plugins`、`plugins_api` 或私有 `update_plugins_*` updater hook；
- 不从 FeiCode、GitHub 或自定义 JSON feed 注入更新包。

这样 WordPress 核心继续按 `wpavatar` slug 使用 WordPress.org 元数据和下载包，不形成双更新源竞争。

## FeiCode 的职责

FeiCode `WenPai-org/wpavatar` 是代码真源、PR 评审入口和经评审 release 资产保存位置。它不是客户端自动更新 feed。

人工发布获批后，FeiCode release 可保存本轮验证过的 `wpavatar-1.9.6.zip` 与 SHA-256，作为审计、回滚和旧 1.9.5 fatal 的安全人工替换来源。不得让 Forgejo 自动生成的源码归档冒充插件 ZIP。

WordPress.org SVN tag 与 FeiCode release ZIP 必须来自同一获批提交；发布人员应对两边包逐文件比对。任何 tag、release、SVN push 和公开发布都属于人工动作，本 PR 和 CI 不自动执行。

## 营销短代码

1.9.6 保留四个既有营销/社区短代码及当前行为。下一主版本才把三个社区列表短代码拆到 add-on，`[wpavatar_author]` 留在主插件；迁移不得删除正文或 option，并必须具备旧站兼容和回滚样本。

## PR CI 与人工发布门

PR 的 `wp-plugin-ci.yml` 自动执行 PHP lint、Composer 依赖安装、元数据/单更新通道门禁、PHPUnit、PHPStan level 5、release PHPCS、Gitleaks 和双构建一致性。Plugin Check、浏览器 E2E、两组 WordPress 真实运行矩阵及 fatal 升级需要 Docker/WordPress 专用环境，当前作为有退出码和日志的人工发布证据，不得误写成远端 CI 已覆盖。

## 发布人工门

1. PR #2 最终 HEAD 的所有必需 CI job 获授权执行并成功；零执行样本不算通过。
2. PHPUnit、PHPStan level 5、release PHPCS、Plugin Check、Gitleaks、E2E、PHP 7.4/WP 5.4 与 PHP 8.3/WP 7 单站/多站矩阵、1.9.5→1.9.6 安全升级均通过。
3. 同一 `SOURCE_DATE_EPOCH` 连续构建两次，ZIP SHA-256 完全一致；包内文件、权限、PHP lint 和元数据通过。
4. 有权限人员审核并合并 PR；随后分别创建不可变 FeiCode release 资产和 WordPress.org SVN tag，再反下载比对。代理不得代替这些人工动作。
