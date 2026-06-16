# AGENTS.md - 项目操作备忘

## 远程仓库清理操作

当需要清理远程 main 分支（只保留说明和安装文件）时，执行以下步骤：

### 保留文件清单

远程仓库只保留以下文件：

```
.gitignore
README.md
install.sh
wepay_1.8.0.1.apk
wxmonitor_v1.4.zip
```

### 操作命令

```powershell
# 1. 从 git 索引移除（不删本地文件）
git rm --cached -r .dockerignore .env .gitattributes .htaccess .travis.yml composer.json composer.lock Dockerfile docker-compose.yml think LICENSE README_old.md app assets config docker .github extend public route runtime vendor view

# 2. 提交（不要用 git add -A，否则会把本地文件加回去）
git commit -m "chore: remove source code, keep only README, install script and tools"

# 3. 推送到远程
git push origin master:main
```

### 注意事项

- **绝对不要用 `git add -A`**，会把本地已删除索引的文件重新加回暂存区
- `git rm --cached` 只从 git 索引移除，本地文件不会被删除
- 提交时只 commit 删除，不 stage 任何新文件
- 本地分支是 `master`，远程分支是 `main`

## 构建后清理流程

编译/构建完成后，如需再次清理远程仓库：

1. 确认本地代码已保存/备份
2. 按上述命令执行 `git rm --cached` + `git commit` + `git push`
3. 验证 `git ls-tree --name-only HEAD` 确认只剩目标文件
