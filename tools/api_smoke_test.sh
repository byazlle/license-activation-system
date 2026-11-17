#!/usr/bin/env bash
# 开放 API 冒烟测试：从本地对远程服务器逐条发真实请求
# 用法：API_KEY=lsk_xxx BASE=http://服务器IP bash tools/api_smoke_test.sh
set -u
KEY="${API_KEY:-}"
BASE="${BASE:-http://127.0.0.1}"
FAILED=0

call() {
    local name="$1" path="$2" body="${3:-{\}}"
    local out
    out=$(curl -s -X POST "$BASE/openapi/$path" \
        -H "Authorization: Bearer $KEY" \
        -H 'Content-Type: application/json' \
        --data-binary "$body")
    local code
    code=$(printf '%s' "$out" | sed -n 's/.*"code":\([0-9-]*\).*/\1/p' | head -n 1)
    if [ "$code" = "0" ]; then
        printf '  OK   %-24s %s\n' "$name" "$(printf '%s' "$out" | head -c 90)"
    else
        printf '  FAIL %-24s %s\n' "$name" "$out"
        FAILED=$((FAILED + 1))
    fi
}

echo "目标：$BASE/openapi"
echo "--- 鉴权与只读 ---"
call ping ping '{}'
call keys/list keys/list '{}'
call keys/self keys/self '{}'
call software/list software/list '{}'
call card/list card/list '{}'
call system/info system/info '{}'
call stats/overview stats/overview '{}'
call stats/card stats/card '{}'
call card/linkstats card/linkstats '{}'
call log/requests log/requests '{"per_page":5}'
call log/calls log/calls '{"limit":5}'
call admin/list admin/list '{}'

echo "--- 鉴权失败用例（预期非 0）---"
NOKEY=$(curl -s -X POST "$BASE/openapi/ping" -d '{}')
printf '  %s no-key 返回：%s\n' "$(echo "$NOKEY" | grep -q '"code":4001' && echo OK || echo FAIL)" "$(echo "$NOKEY" | head -c 90)"

echo "失败数：$FAILED"
exit "$FAILED"
