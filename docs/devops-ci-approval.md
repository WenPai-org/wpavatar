# WPAvatar PR #2 FeiCode CI 审批任务说明

> [CX] `feicode-prod` 为 P0，`write_policy=board_required`。本说明供 devops 创建并审核 Task Board change/control 任务；Codex 不自行批准 fork PR CI，不合并 PR，不创建 release，不发布 WordPress.org，不部署站点。

## 任务目标

对 `https://feicode.com/WenPai-org/wpavatar/pulls/2` 的**执行时精确 HEAD**放行 fork pull-request workflows，并取得非零 job/step 样本。HEAD 变化时立即停止，重新建立审批对象，禁止把旧 SHA 结果套到新 SHA。

## 执行前输入

- PR：`WenPai-org/wpavatar#2`
- 预期 HEAD：由提交人通过 task-request 提供；devops 必须再调用 FeiCode API 复核。
- 允许 workflow：`gitleaks.yml`、`security-scan.yml`、`wp-plugin-ci.yml`。
- 禁止动作：merge、rebase、force-push main、tag、release、WordPress.org SVN push、生产部署、修改 Forgejo/WAF/runner 全局配置。

## Task Board 建议字段

- family：`control`（或 devops 现行等价的 `change`）
- owner：`devops`
- target：`devops`
- review_required：`true`
- capability：FeiCode/Forgejo Actions 审批
- acceptance：仅对预期 HEAD 的三个 fork-PR workflow 执行一次授权；记录 run ID、job ID、step 结论和日志入口；不触发任何发布或部署。

## 执行步骤

1. 读取 PR API，确认 `open=true`、`merged=false` 且 `head.sha` 等于 Board/task-request 中的预期 HEAD。
2. 查询该 SHA 的 statuses 和 Actions runs，确认三项均属于 PR #2 且仍为 `need_approval=true`；不得审批其它 PR 或其它 SHA。
3. 在 FeiCode Actions 的 Manage approval 中只批准本次三个 run。
4. 等待每个 job 实际结束；记录 workflow/run/job/step 结论。`waiting`、`pending`、空 `stepsLog` 或 HTTP 404 日志均是零样本，不得写 PASS。
5. 任一 job 失败时只回传日志和失败分类，不修改代码、不重跑发布、不合并。
6. 三项成功后把证据写回 Board result；任务进入 review，由 devops owner 审核。

## 验收输出

- exact PR HEAD；
- 三个 workflow 的 run ID、job ID、最终 conclusion；
- 每个 job 的非空 step 列表和日志入口；
- Board task/project ID 与审批记录；
- 明确声明未 merge、release、publish 或 deploy。
