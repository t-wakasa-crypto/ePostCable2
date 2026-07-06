#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

CONTAINERS=(php worker scheduler)

echo "IPAexフォント (fonts-ipaexfont) を各コンテナにインストール中..."
for svc in "${CONTAINERS[@]}"; do
    echo "  -> $svc"
    docker compose exec --user root "$svc" bash -c "
        apt-get update -qq &&
        apt-get install -y --no-install-recommends fonts-ipaexfont -q &&
        mkdir -p /usr/share/fonts/truetype/fonts-ipa &&
        ln -sf /usr/share/fonts/opentype/ipaexfont-gothic/ipaexg.ttf /usr/share/fonts/truetype/fonts-ipa/ipaexg.ttf
    "
done

echo "dompdf に IPAexGothic フォントを登録しています (dompdf:load-fonts)..."
docker compose exec -T php php artisan dompdf:load-fonts

echo "完了: 日本語フォントの設定が完了しました。"
echo "※ IPAexGothic を storage/fonts に事前登録しました（FR-15・LoadDompdfFonts）。"
echo "   これにより請求書・納品書 PDF の日本語が文字化けせず描画されます。"
echo "※ コンテナを再作成した場合は再度このスクリプトを実行してください。"
echo "   (永続化するには docker/php/Dockerfile に fonts-ipaexfont を追加してください)"
