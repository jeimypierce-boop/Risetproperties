$url = 'https://ngrok.com/download'
$html = Invoke-WebRequest -Uri $url -UseBasicParsing
$matches = [regex]::Matches($html.Content, 'https://[^"\s>]+\.zip') | ForEach-Object { $_.Value }
$matches | Sort-Object -Unique | ForEach-Object { Write-Output $_ }
