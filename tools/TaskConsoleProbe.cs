using System;
using System.Diagnostics;
using System.IO;
using System.Management;
using System.Runtime.InteropServices;
using System.Threading;
using System.Collections.Generic;

internal static class TaskConsoleProbe {
    [DllImport("kernel32.dll", SetLastError=true)] static extern bool AttachConsole(uint pid);
    [DllImport("kernel32.dll")] static extern bool FreeConsole();
    [DllImport("kernel32.dll")] static extern IntPtr GetConsoleWindow();
    [DllImport("user32.dll")] static extern bool IsWindowVisible(IntPtr hwnd);
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Auto)] struct Entry {
        public uint size,usage,pid; public IntPtr heap; public uint module,threads,parent; public int priority; public uint flags;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst=260)] public string name;
    }
    [DllImport("kernel32.dll")] static extern IntPtr CreateToolhelp32Snapshot(uint flags,uint pid);
    [DllImport("kernel32.dll",CharSet=CharSet.Auto)] static extern bool Process32First(IntPtr snapshot,ref Entry entry);
    [DllImport("kernel32.dll",CharSet=CharSet.Auto)] static extern bool Process32Next(IntPtr snapshot,ref Entry entry);
    [DllImport("kernel32.dll")] static extern bool CloseHandle(IntPtr handle);
    static List<Entry> Processes() {
        var entries=new List<Entry>(); IntPtr snapshot=CreateToolhelp32Snapshot(2,0);
        if(snapshot==new IntPtr(-1)) return entries;
        try { var entry=new Entry(); entry.size=(uint)Marshal.SizeOf(typeof(Entry));
            if(Process32First(snapshot,ref entry)) do { entries.Add(entry); } while(Process32Next(snapshot,ref entry));
        } finally { CloseHandle(snapshot); }
        return entries;
    }
    static readonly object Gate = new object();
    static string output;
    static void Record(uint pid, string name, uint parent) {
        lock(Gate) {
            string job="other";
            try {
                bool attached=AttachConsole(pid); IntPtr window=attached?GetConsoleWindow():IntPtr.Zero;
                bool visible=window!=IntPtr.Zero && IsWindowVisible(window);
                if(attached) FreeConsole();
                using(var search=new ManagementObjectSearcher("SELECT CommandLine,ParentProcessId FROM Win32_Process WHERE ProcessId="+pid)) {
                    foreach(ManagementObject p in search.Get()) {
                        string command=Convert.ToString(p["CommandLine"]);
                        if(command.Contains("payments:maintain")) job="maintenance";
                        else if(command.Contains("Ensure-PresentationSupport.ps1")) job="presentation";
                        else if(command.Contains("Start-RecoveredMariaDB.ps1")) job="database";
                        else if(command.Contains("FacultyConnectTask.exe")) job="launcher";
                    }
                }
                File.AppendAllText(output, String.Format("{{\"at\":\"{0:o}\",\"pid\":{1},\"parent\":{2},\"name\":\"{3}\",\"job\":\"{4}\",\"console_attached\":{5},\"console_window\":{6},\"visible_flag\":{7}}}\n",DateTimeOffset.Now,pid,parent,name,job,attached.ToString().ToLowerInvariant(),window.ToInt64(),visible.ToString().ToLowerInvariant()));
            } catch { File.AppendAllText(output,"{\"probe_race_or_access_failure\":true,\"pid\":"+pid+"}\n"); }
        }
    }
    static int Main(string[] args) {
        if(args.Length!=2) return 2;
        output=args[0]; int seconds=int.Parse(args[1]);
        try {
            var seen=new HashSet<int>(); string[] names={"php","powershell","cmd"};
            foreach(var p in Processes()) seen.Add((int)p.pid);
            File.AppendAllText(output,"{\"polling_probe_started\":\""+DateTimeOffset.Now.ToString("o")+"\"}\n");
            var timer=Stopwatch.StartNew();
            while(timer.Elapsed.TotalSeconds<seconds) {
                foreach(var p in Processes()) if((p.name=="php.exe" || p.name=="powershell.exe" || p.name=="cmd.exe") && seen.Add((int)p.pid)) Record(p.pid,p.name,p.parent);
                Thread.Sleep(20);
            }
            return 0;
        } catch { File.AppendAllText(output,"{\"probe_unavailable\":true}\n"); return 1; }
    }
}
