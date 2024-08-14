# パラメータの取得
param([string]$AppName)

# インストールされているパッケージの一覧を取得
foreach($app in get-AppxPackage) {
    # 指定のパッケージと一致するものを探す
    if ($app.Name -notlike "*$AppName*") {
        continue
    }
    # パッケージ情報からパッケージIDを取得
    foreach ($id in (Get-AppxPackageManifest $app).package.applications.application.id) {
        # 起動可能なコマンドを作成
        $command = "shell:Appsfolder\" + $app.packagefamilyname + "!" + $id
        echo $command
    }
}