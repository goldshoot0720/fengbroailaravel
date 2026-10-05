#!/bin/sh
# 在 alwaysdata 家目錄執行。只更新 www/fengbroailaravel，不建立第二份複本。
set -eu

live="$HOME/www/fengbroailaravel"

rm -rf "$HOME/fengbroailaravel-update"

if [ ! -d "$live" ]; then
  echo "找不到 $live" >&2
  exit 1
fi

cd "$live"

if [ ! -d .git ]; then
  echo "www/fengbroailaravel 不是 git 目錄，無法在原地覆蓋。" >&2
  echo "請從本機 rsync 到 www/fengbroailaravel/，不要再同步到 fengbroailaravel-update。" >&2
  exit 1
fi

git fetch origin
git checkout -f main
git reset --hard origin/main

echo "已直接覆蓋 $live"
