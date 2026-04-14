Option Explicit

Dim shell
Dim fso
Dim projectDir
Dim logDir
Dim logFile
Dim phpWinPath
Dim phpExePath
Dim phpPath
Dim cmd
Dim execObj
Dim outText
Dim errText
Dim ts
Dim startStamp
Dim endStamp
Dim exitCode

Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

projectDir = fso.GetParentFolderName(WScript.ScriptFullName)
projectDir = fso.GetParentFolderName(projectDir)
logDir = projectDir & "\var\log"
logFile = logDir & "\scheduled-transfers.log"

If Not fso.FolderExists(logDir) Then
	fso.CreateFolder(logDir)
End If

phpWinPath = "C:\xampp\php\php-win.exe"
phpExePath = "C:\xampp\php\php.exe"
If fso.FileExists(phpWinPath) Then
	phpPath = phpWinPath
Else
	phpPath = phpExePath
End If

startStamp = FormatDateTime(Now, 0) & " " & FormatDateTime(Now, 4)
Set ts = fso.OpenTextFile(logFile, 8, True)
ts.WriteLine "[" & startStamp & "] START app:transfers:run-scheduled"
ts.Close

cmd = """" & phpPath & """ " & _
	  """" & projectDir & "\bin\console"" " & _
	  "app:transfers:run-scheduled --env=prod --no-interaction"

shell.CurrentDirectory = projectDir
Set execObj = shell.Exec(cmd)

Do While execObj.Status = 0
	WScript.Sleep 100
Loop

outText = execObj.StdOut.ReadAll()
errText = execObj.StdErr.ReadAll()
exitCode = execObj.ExitCode

Set ts = fso.OpenTextFile(logFile, 8, True)
If Len(outText) > 0 Then
	ts.Write outText
	If Right(outText, 1) <> vbCr And Right(outText, 1) <> vbLf Then
		ts.WriteLine ""
	End If
End If
If Len(errText) > 0 Then
	ts.Write errText
	If Right(errText, 1) <> vbCr And Right(errText, 1) <> vbLf Then
		ts.WriteLine ""
	End If
End If
endStamp = FormatDateTime(Now, 0) & " " & FormatDateTime(Now, 4)
ts.WriteLine "[" & endStamp & "] END code=" & CStr(exitCode)
ts.Close
