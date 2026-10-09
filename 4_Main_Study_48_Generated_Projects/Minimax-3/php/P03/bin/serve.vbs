Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "D:\000_phd_graudaiton\EMSE\MiniMax3\php\P03"
WshShell.Run "cmd /c php -S 127.0.0.1:8099 -t public public/index.php", 0, False
WScript.Quit 0
