# WPAvatar 1.9.5 安全升级入口

发布标签 `v1.9.5` 的 `includes/multisite.php` 含多处额外右括号；WordPress 在加载插件主文件时无条件 `require_once` 该文件，所以单站和多站都会在 PHP 解析阶段 fatal。不要通过正常后台请求、普通 `wp plugin deactivate` 或先激活旧包来升级。

## 更新通道与候选来源

- 1.9.6 的最低运行环境为 WordPress 5.4、PHP 7.4。
- 普通公开更新只通过 WordPress.org 的 `wpavatar` 插件通道；主插件不声明 `Update URI`，也不注册 FeiCode 自定义更新器。
- FeiCode 是代码真源和经评审 release ZIP 的保存位置，不是第二条自动更新 feed。只有在旧 1.9.5 parse fatal 使正常后台升级不可用时，才按本入口人工取得已审批的 FeiCode 资产并核对 SHA-256。
- WordPress.org 包与 FeiCode release 资产必须来自同一审批提交并逐文件核对；本文不创建 tag、release 或 WordPress.org SVN 发布。

## 操作边界

1. 进入维护窗口，先备份数据库和完整的 `wp-content/plugins/wpavatar`。不要删除选项、缓存或旧插件目录。
2. 用不加载任何插件/主题的 WP-CLI 停用旧插件：
   - 单站：`wp --skip-plugins --skip-themes plugin deactivate wpavatar`
   - 多站：`wp --skip-plugins --skip-themes plugin deactivate wpavatar --network`
3. 在站点外解压候选 ZIP，核对审批记录中的 SHA-256，并对解压目录内所有 PHP 文件执行 `php -l`。本轮候选必须同时报告 `Version: 1.9.6`、`Stable tag: 1.9.6`、`Requires PHP: 7.4` 和经审批的 ZIP SHA-256；不能只凭文件名判断包身份。
4. 在与现有插件相同的文件系统创建候选暂存目录。先把现有目录改名为带时间戳的 rollback 目录，再把已校验的候选暂存目录改名为 `wpavatar`；不要覆盖式复制共享目录。
5. 单站执行 `wp --skip-plugins --skip-themes plugin activate wpavatar`；多站执行同命令并加 `--network`。随后验证前台、后台、单站 option、network site_option、子站 option 和已有缓存。
6. 激活失败时，让候选保持停用，将失败目录另行保留，恢复旧目录但不要重新激活存在 parse fatal 的旧版；从数据库/文件备份回滚或改用已评审候选。

`safe-replace.sh` 把上述数据库备份、代码备份、候选 lint、skip-plugins 停用、同文件系统目录切换和激活串成一个明确入口：

```bash
# 单站
sh tests/upgrade/safe-replace.sh /var/www/html /path/to/unpacked/wpavatar /path/to/backups single

# 多站
sh tests/upgrade/safe-replace.sh /var/www/html /path/to/unpacked/wpavatar /path/to/backups network
```

该脚本不会删除数据库、选项、缓存、旧插件目录或备份。它不负责下载候选包，也不绕过候选 ZIP 的人工审批。

## 可重复验证

`run.sh` 从真实 `v1.9.5` 标签构造两个 fixture：未改发布源用于复现 fatal；另一个只应用 `legacy-1.9.5-syntax.patch`，用于让旧运行时先写入真实单站/多站配置。测试随后重新注入未改发布源的 fatal 文件，通过上述安全入口升级候选，并验证：

- 单站 option、任意 sentinel option、已有缓存文件均保留；
- network site_option、受控选项数组、子站 option、主站/子站缓存均保留；
- 候选恢复原来的单站或网络启用状态；
- 数据库导出、旧代码备份和 rollback 目录存在。

```bash
bash tests/upgrade/run.sh
```

测试只使用独立 Docker project 和临时卷，不连接生产站点，也不发布、部署或合并分支。
