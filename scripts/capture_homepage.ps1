$chrome = "C:\Program Files\Google\Chrome\Application\chrome.exe"
$outDir = "C:\Users\Acer\.gemini\antigravity\brain\20a9971b-e58a-43e6-b482-48436e3bae68"

Write-Host "Capturing Desktop 1440x900..."
$arg1 = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=1440,900", "--screenshot=$outDir\homepage_desktop_1440.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $arg1 -Wait

Write-Host "Capturing Laptop 1280x800..."
$arg2 = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=1280,800", "--screenshot=$outDir\homepage_laptop_1280.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $arg2 -Wait

Write-Host "Capturing Tablet 768x1024..."
$arg3 = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=768,1024", "--screenshot=$outDir\homepage_tablet_768.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $arg3 -Wait

Write-Host "Capturing Mobile 390x844..."
$arg4 = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=390,844", "--screenshot=$outDir\homepage_mobile_390.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $arg4 -Wait

Write-Host "Capturing Desktop Full Page (1440x4200)..."
$argFull = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=1440,4200", "--screenshot=$outDir\homepage_desktop_full.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $argFull -Wait

Write-Host "Capturing Mobile Full Page (390x5600)..."
$argMobileFull = @("--headless=new", "--disable-gpu", "--virtual-time-budget=6000", "--window-size=390,5600", "--screenshot=$outDir\homepage_mobile_full.png", "http://127.0.0.1:3000/#/")
Start-Process -FilePath $chrome -ArgumentList $argMobileFull -Wait

Write-Host "Done. Captured screenshots:"
Get-ChildItem -Path $outDir -Filter "homepage_*.png" | Select-Object Name, Length, LastWriteTime
