# WPAvatar 营销短代码兼容迁移决策

> [CX] 状态：用户已批准迁移方向；1.9.6 只固化兼容边界，拆包实现进入下一主版本。本文不授权发布、部署或删除用户数据。

## 1. 结论

本轮保留现有四个短代码，不改正文，不删除设置数据：

- 核心继续拥有 `[wpavatar_author]`。
- `[wpavatar_latest_commenters]`、`[wpavatar_latest_users]`、`[wpavatar_random_users]` 作为可拆分的营销/社区展示功能。
- 1.9.6 保持四个短代码、设置、查询和样式的现有行为，不增加默认关闭开关，不要求安装 add-on。
- 下一主版本由独立的 `WPAvatar Marketing` add-on 接管前三个社区列表短代码。主插件在兼容窗口内保留适配器，窗口结束后仍保留无查询的 no-op shim，避免正文显示原始 shortcode。
- 任何阶段都不自动删除旧 option、不改写 `post_content`、不因停用 add-on 清理用户数据。

这项产品迁移方向已经批准，但当前 1.9.6 评审候选不实施拆包或默认行为变更。

## 2. 现状与边界

当前 `WPAvatar\Marketing::init()` 无条件注册四个短代码，并在 `wp_head` 无条件挂载营销样式。前三个短代码会读取评论或用户数据；四个既有设置为：

- `wpavatar_commenters_count`
- `wpavatar_commenters_size`
- `wpavatar_users_count`
- `wpavatar_users_size`

单站通过 WordPress site 的 `options`（`get_option()/update_option()`）保存；多站网络层通过 `sitemeta`（`get_site_option()/update_site_option()`）保存。现有 `wpavatar_network_controlled_options` 和 `wpavatar_network_enforce` 已提供网络覆盖语义。

本次拆分不包括核心头像替换、缓存、Cravatar/Gravatar 路由、`[wpavatar]`、`[wpavatar_username]` 或 `[wpavatar_author]`。

## 3. 下一主版本的开关与数据模型

### 3.1 下一主版本新增的键

| 键 | 单站/子站 | 网络层 | 用途 |
|---|---|---|---|
| `wpavatar_enable_marketing` | `0/1`，新安装默认 `0` | `0/1` | 是否启用前三个社区展示短代码 |
| `wpavatar_marketing_migration_version` | 迁移版本字符串 | 迁移版本字符串 | 保证迁移幂等，区分新安装和旧安装 |
| `wpavatar_marketing_addon_notice_dismissed` | 管理界面提示状态 | 可选 | 只影响提示，不影响功能 |

不另造 `wpavatar_network_enable_marketing`。网络值沿用同名 `wpavatar_enable_marketing`，并按现有控制规则解析：

1. 非多站：读取 site option `wpavatar_enable_marketing`。
2. 多站且 `wpavatar_network_enforce=1`：网络值优先。
3. 多站且 `wpavatar_enable_marketing` 在 `wpavatar_network_controlled_options` 中：网络值优先。
4. 其余多站场景：读取当前子站 option；没有值时默认关闭。

网络管理员必须显式选择是否把该键加入 controlled options。迁移不得顺手改变现有 `wpavatar_network_enforce` 或其它受控选项。

### 3.2 下一主版本新安装默认关闭

“新安装”必须由迁移前的数据库状态判断，不能依赖激活钩子稍后创建的四个默认设置：

- 在创建 legacy 默认 option 之前记录安装类型，或使用首次安装/schema marker。
- 全新站点没有迁移 marker，也没有任何四个 legacy marketing option：写入 `wpavatar_enable_marketing=0`。
- 禁用状态不得查询评论/用户，不得输出营销 CSS。
- `[wpavatar_author]` 不受此开关影响。

### 3.3 从 1.9.6 升级到下一主版本的兼容迁移

升级不能只放在 activation hook；WordPress 覆盖更新已启用插件时不会重新触发激活。应在有版本门禁的正常加载路径执行一次幂等迁移。

旧站判定：

- `wpavatar_marketing_migration_version` 不存在；且
- 四个 legacy marketing option 至少一个在迁移开始前已经存在。

符合条件时写入 `wpavatar_enable_marketing=1`，保留原值并记录迁移版本。这样旧站升级后页面输出不变。不存在旧设置的新安装写入 `0`。

多站迁移采用两层策略：

- 网络层：若已有任一网络 legacy option，则网络开关迁移为 `1`；否则为 `0`。
- 子站层：首次访问/加载该站时惰性迁移，或由明确的 WP-CLI 批处理命令迁移。不要在普通请求中一次遍历大型网络全部站点。
- 网络强制模式继续遵守网络值；不强制时，各子站按自身 legacy option 判定。
- 每层独立记录 migration version，重跑不得覆盖管理员已明确保存的 `0/1`。

## 4. 短代码与 add-on 所有权

### 4.1 主插件长期保留

- `[wpavatar]`、`[wpavatar_username]`、`[wpavatar_author]`。
- `wpavatar_enable_marketing` 的有效值解析。
- 迁移 marker、兼容状态检查和 no-op shim。
- add-on 存在性与最低兼容版本检查。

`[wpavatar_author]` 只渲染当前文章作者头像，属于头像核心场景，不随社区用户枚举功能拆出。

### 4.2 Add-on 接管

`WPAvatar Marketing` add-on 最终拥有：

- `[wpavatar_latest_commenters]`
- `[wpavatar_latest_users]`
- `[wpavatar_random_users]`
- 对应四个显示设置、查询实现、前端 CSS 和管理界面。
- 对旧 option 的读取兼容；第一阶段继续使用原键，避免复制或丢失设置。

主插件与 add-on 必须通过明确的类/常量或 hook 协商所有权，不能靠加载顺序互相 `remove_shortcode()`。同一请求中每个 tag 只能有一个 owner。

### 4.3 兼容适配器与 no-op shim

迁移窗口内按以下优先级：

1. marketing 开关为 `0`：主插件注册 no-op shim；返回空字符串，不查询评论/用户、不挂营销 CSS。
2. marketing 开关为 `1` 且兼容 add-on 已启用：add-on 注册三个 renderer，主插件不注册同名 renderer。
3. marketing 开关为 `1`、站点由旧版本迁移且 add-on 未启用：在一个完整版本的兼容窗口内，由主插件 bundled legacy adapter 保持现有输出，并只向有权限的管理员显示迁移提示。
4. add-on 版本不兼容：fail closed 到兼容适配器或 no-op，不产生 fatal，不允许两个 owner 同时注册。

兼容窗口结束后可以移除 bundled legacy 查询实现，但主插件仍保留 no-op shim。这样停用 add-on 时正文不会暴露原始 shortcode，也不会偷偷执行高成本用户/评论查询。是否永久保留 shim 应在移除 bundled renderer 前再次评审。

no-op shim 不得删除数据，也不得把停用解释为卸载。可提供 filter 让站点在受控代码中改变占位输出，但默认必须为空且经过标准 shortcode 输出安全约束。

## 5. 已批准时间线

### 1.9.6：本次评审候选

- 四个现有短代码继续由主插件注册并保持现有输出，不删除、不默认关闭、不拆包。
- 不新增 `wpavatar_enable_marketing`，不提前写 migration marker，不要求安装 add-on。
- 只记录下一主版本的数据所有权、迁移、回滚和验收契约。
- PHP 最低版本为 7.4；公开更新只走 WordPress.org，FeiCode 保存代码真源和经评审的 release 资产。

### 下一主版本：add-on 拆分

- 发布独立 `WPAvatar Marketing` add-on，由它接管三个社区列表短代码、四个设置和样式。
- `[wpavatar_author]` 留在主插件。
- 旧站依据 legacy option 做幂等迁移；已启用站点在兼容窗口内保持原输出，新安装默认不启用社区列表查询。
- 已迁移且启用、但 add-on 未启用的站点进入 bundled adapter 或 no-op 的明确兼容路径，不得显示原始 shortcode。
- 主插件/add-on 使用同一组 legacy option，不删除或复制用户正文与设置；回滚时恢复主插件兼容路径。
- 移除 bundled renderer 前必须重新核对 add-on 接管率、回滚样本和兼容问题；零样本不得删除。

## 6. 回滚与数据保留

- 升级、禁用营销、停用 add-on、降级主插件均不删除上述四个 legacy option、开关或 migration marker。
- 不自动搜索替换文章、模板、widget 或 block 中的 shortcode。
- 从下一主版本回滚到 1.9.6 时，主插件继续读取同一组 legacy option；add-on 不拥有独占数据格式。
- add-on 停用后，兼容窗口内回到 bundled adapter；窗口结束后回到 no-op shim，不 fatal、不显示原始 shortcode。
- 管理员重新启用开关或 add-on 后，原设置应立即恢复生效。
- 卸载时是否删除数据必须沿用 WPAvatar 的全局卸载策略并单独取得用户决策；本迁移不新增隐式清理。
- 多站回滚不得批量删除 `sitemeta` 或逐站 options；网络值与子站值分别保留。

## 7. 验收标准

以下全部有非零样本和退出码证据后，迁移实现才可评审为完成：

1. **新安装单站：** 开关为 `0`；三个社区 shortcode 不查询用户/评论、无营销 CSS、页面不显示原始 shortcode；`[wpavatar_author]` 正常。
2. **1.9.5 旧站升级：** 四个 legacy option 和页面输出保持；开关迁移为 `1`；迁移重复执行不改变管理员选择。
3. **显式禁用：** 三个社区 shortcode 走 no-op，查询计数为零；缓存、头像替换和核心 shortcode 不受影响。
4. **多站非强制：** 至少两个子站分别验证 `0/1`，互不覆盖；网络和子站 migration marker 独立。
5. **多站强制/controlled：** 网络值按既有优先级覆盖子站，撤销控制后恢复子站原值，过程中不删数据。
6. **Add-on 接管：** 每个 tag 只有一个 owner；输出和转义与拆分前一致；主插件不重复挂 CSS。
7. **Add-on 缺失、停用、过旧：** 分别验证 bundled adapter/no-op 路径，无 fatal、无原始 shortcode、无双重注册。
8. **回滚：** 下一主版本→1.9.6、1.9.6→1.9.5 的数据库快照对比不丢 option，不改 `post_content`；旧版本忽略未知新键。
9. **性能：** 禁用路径的评论/用户查询为零；多站迁移不在普通请求全网遍历。
10. **发布包：** 主插件包不意外包含 add-on 源码；add-on 声明主插件最低版本；两包分别通过 PHP lint、Plugin Check、PHPCS 和真实 WordPress 运行验证。

## 8. 已批准决策与实现期留项

已批准且不得在 1.9.6 中反向改变的决策：

- 候选版本为 1.9.6，最低 PHP 为 7.4。
- 公开更新只走 WordPress.org；FeiCode 是代码真源和经评审 release 资产保存位置，不建立第二条自动更新通道。
- 1.9.6 不删除或拆分现有营销/社区列表短代码。
- 下一主版本拆为 add-on，必须保留旧站兼容迁移、正文/option 数据和可验证回滚。

下一主版本实现时仍需用测试和评审确定兼容窗口的精确长度、管理员提示文案和 add-on 发布日期；这些留项不阻塞 1.9.6，也不授权当前版本提前改变行为。
