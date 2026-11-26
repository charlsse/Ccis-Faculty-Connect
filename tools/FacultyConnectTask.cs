using System;
using System.Diagnostics;
using System.IO;
using System.Text;
using System.Threading;
using System.Runtime.InteropServices;

// Compile as Windows GUI subsystem: no console exists before the original command starts.
internal static class FacultyConnectTask {
    const string Project=@"C:\xampp\htdocs\CCIS_CONNECT";
    const string Logs=@"C:\Users\Charles\FacultyConnect-Recovery\task-runtime";
    [DllImport("kernel32.dll", SetLastError=true)] static extern bool AttachConsole(uint pid);
    [DllImport("kernel32.dll")] static extern bool FreeConsole();
    static void Append(string path,string text) {
        if(File.Exists(path) && new FileInfo(path).Length+Encoding.UTF8.GetByteCount(text)>1048576) {
            if(File.Exists(path+".previous")) File.Delete(path+".previous");
            File.Move(path,path+".previous");
        }
        File.AppendAllText(path,text,Encoding.UTF8);
    }
    static void Capture(StringBuilder output,string line) {
        if(line==null) return;
        lock(output) { if(output.Length<65536) output.AppendLine(line.Length>4096?line.Substring(0,4096)+" [line truncated]":line); }
    }
    [STAThread] static int Main(string[] args) {
        if(args.Length!=1) return 64;
        string job=args[0],exe,arguments; int timeout;
        switch(job) {
            case "maintenance": exe=@"C:\xampp\php\php.exe"; arguments="\""+Project+"\\artisan\" payments:maintain --no-email"; timeout=295000; break;
            case "presentation": exe=Environment.GetFolderPath(Environment.SpecialFolder.System)+@"\WindowsPowerShell\v1.0\powershell.exe"; arguments="-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File \""+Project+"\\tools\\Ensure-PresentationSupport.ps1\""; timeout=175000; break;
            case "database": exe=Environment.GetFolderPath(Environment.SpecialFolder.System)+@"\WindowsPowerShell\v1.0\powershell.exe"; arguments="-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File \"C:\\Users\\Charles\\FacultyConnect-Recovery\\20261012\\Start-RecoveredMariaDB.ps1\""; timeout=55000; break;
            // Prepared for the separately approved backup action; never invoked by maintenance.
            case "backup": exe=@"C:\xampp\php\php.exe"; arguments="\""+Project+"\\tools\\backup-recovered-database.php\" \"C:\\Users\\Charles\\FacultyConnect-Recovery\\backup.env\""; timeout=1795000; break;
            case "probe-success": exe=@"C:\xampp\php\php.exe"; arguments="-r \"fwrite(STDOUT, 'launcher stdout verification'); fwrite(STDERR, 'launcher stderr verification'); usleep(1500000); exit(0);\""; timeout=10000; break;
            case "probe-failure": exe=@"C:\xampp\php\php.exe"; arguments="-r \"fwrite(STDERR, 'launcher nonzero verification'); exit(7);\""; timeout=10000; break;
            case "probe-timeout": exe=@"C:\xampp\php\php.exe"; arguments="-r \"usleep(1500000);\""; timeout=100; break;
            default: return 64;
        }
        bool owned=false;
        using(var mutex=new Mutex(false,"Local\\FacultyConnectQuietTask-"+job)) {
            try {
                try { owned=mutex.WaitOne(0); } catch(AbandonedMutexException) { owned=true; }
                if(!owned) return 0;
                Directory.CreateDirectory(Logs);
                var output=new StringBuilder(); var error=new StringBuilder();
                using(var child=new Process()) {
                    try {
                    child.StartInfo=new ProcessStartInfo(exe,arguments) {
                        WorkingDirectory=Project, UseShellExecute=false, CreateNoWindow=true,
                        RedirectStandardInput=true, RedirectStandardOutput=true, RedirectStandardError=true
                    };
                    // Symfony otherwise probes stty / mode CON through cmd.exe on Windows.
                    // Fixed dimensions apply only to unattended log formatting, never the app.
                    child.StartInfo.EnvironmentVariables["COLUMNS"]="120";
                    child.StartInfo.EnvironmentVariables["LINES"]="40";
                    child.StartInfo.EnvironmentVariables["ANSICON"]="120x40";
                    child.StartInfo.EnvironmentVariables["NO_COLOR"]="1";
                    child.OutputDataReceived+=(s,e)=>Capture(output,e.Data);
                    child.ErrorDataReceived+=(s,e)=>Capture(error,e.Data);
                    var started=DateTimeOffset.Now;
                    child.Start(); child.StandardInput.Close(); child.BeginOutputReadLine(); child.BeginErrorReadLine();
                    bool console=AttachConsole((uint)child.Id); if(console) FreeConsole();
                    Append(Path.Combine(Logs,job+".runs.jsonl"),String.Format("{{\"event\":\"started\",\"at\":\"{0:o}\",\"launcher_pid\":{1},\"child_pid\":{2},\"console_attached\":{3}}}\n",started,Process.GetCurrentProcess().Id,child.Id,console.ToString().ToLowerInvariant()));
                    bool completed=child.WaitForExit(timeout);
                    if(!completed) { child.Kill(); child.WaitForExit(); }
                    else child.WaitForExit(); // Drain asynchronous redirected streams.
                    int result=completed?child.ExitCode:124;
                    Append(Path.Combine(Logs,job+".output.log"),"\n["+started.ToString("o")+"] exit="+result+"\nSTDOUT:\n"+output+"STDERR:\n"+error);
                    Append(Path.Combine(Logs,job+".runs.jsonl"),String.Format("{{\"event\":\"completed\",\"at\":\"{0:o}\",\"child_pid\":{1},\"exit_code\":{2},\"timed_out\":{3}}}\n",DateTimeOffset.Now,child.Id,result,(!completed).ToString().ToLowerInvariant()));
                    return result;
                    } finally {
                        // Also reap a still-running child if logging or stream setup fails.
                        try { if(!child.HasExited) { child.Kill(); child.WaitForExit(5000); } } catch {}
                    }
                }
            } catch {
                try { Append(Path.Combine(Logs,job+".runs.jsonl"),"{\"event\":\"launcher_failed\",\"at\":\""+DateTimeOffset.Now.ToString("o")+"\",\"exit_code\":125}\n"); } catch {}
                return 125;
            } finally { if(owned) mutex.ReleaseMutex(); }
        }
    }
}
