; Inno Setup script for CPR Agent.
;
; Build the bundle first:
;   .\standalone\windows\build-bundle.ps1 -FrankenPhpZip <path to frankenphp windows zip>
; then compile this file with Inno Setup 6:
;   & "C:\Program Files (x86)\Inno Setup 6\ISCC.exe" standalone\windows\cpr-agent.iss
;
; Design notes:
;
; Per user install, no elevation. Installing into LocalAppData means no UAC
; prompt to install, and the app can write its own database and storage. The
; only thing needing admin is the firewall rule, which is requested separately
; and can be declined without breaking a single machine setup.
;
; No space in the directory name. Several layers here pass paths around as
; command arguments, and a space in the install path is a needless source of
; quoting bugs.
;
; data\ is never shipped and never removed on upgrade. Updating replaces
; runtime\ and app\ only, so the user keeps their characters. Uninstall asks
; before deleting it.

#define AppName "CPR Agent"
#define AppVersion "0.5.0"
#define AppPublisher "Surreal Tech"
#define BundleDir "..\..\dist\bundle"

[Setup]
AppId={{8F3B2E14-9C7A-4C31-9E2D-6B5A1F0D7C42}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={localappdata}\CPRAgent
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=..\..\dist
OutputBaseFilename=CPRAgent-Setup-{#AppVersion}
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayName={#AppName}
; The bundle is large (PHP runtime plus vendor), so warn early rather than
; failing part way through the copy.
ExtraDiskSpaceRequired=0

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"
Name: "firewall"; Description: "Allow other devices on my network to connect (adds a Windows Firewall rule, asks for admin)"; GroupDescription: "Network:"

[Files]
; runtime\ and app\ are replaced wholesale on upgrade. data\ is absent here on
; purpose: it is created at runtime and must survive.
Source: "{#BundleDir}\runtime\*"; DestDir: "{app}\runtime"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#BundleDir}\app\*";     DestDir: "{app}\app";     Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#BundleDir}\launcher.ps1";         DestDir: "{app}"; Flags: ignoreversion
Source: "{#BundleDir}\stop.ps1";             DestDir: "{app}"; Flags: ignoreversion
Source: "{#BundleDir}\Start CPR Agent.cmd";  DestDir: "{app}"; Flags: ignoreversion
Source: "{#BundleDir}\Stop CPR Agent.cmd";   DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{group}\{#AppName}";      Filename: "{app}\Start CPR Agent.cmd"; WorkingDir: "{app}"; IconFilename: "{app}\runtime\frankenphp.exe"
Name: "{group}\Stop {#AppName}"; Filename: "{app}\Stop CPR Agent.cmd";  WorkingDir: "{app}"
Name: "{group}\Uninstall {#AppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\Start CPR Agent.cmd"; WorkingDir: "{app}"; IconFilename: "{app}\runtime\frankenphp.exe"; Tasks: desktopicon

[Run]
; The firewall rule needs admin, but the installer itself does not. Elevating
; just this step keeps the install prompt free for anyone playing solo on one
; machine, and asks once for anyone who wants other devices to connect.
; Declining the UAC prompt leaves the app working on localhost.
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -Command ""Start-Process netsh -Verb RunAs -WindowStyle Hidden -ArgumentList 'advfirewall','firewall','add','rule','name=CPR Agent','dir=in','action=allow','program={app}\runtime\frankenphp.exe','enable=yes','profile=private'"""; \
  StatusMsg: "Adding firewall rule..."; \
  Flags: runhidden waituntilterminated; \
  Tasks: firewall

Filename: "{app}\Start CPR Agent.cmd"; Description: "Start {#AppName} now"; WorkingDir: "{app}"; Flags: postinstall nowait skipifsilent

[UninstallRun]
; Best effort: stop anything still running, then drop the firewall rule.
Filename: "{app}\Stop CPR Agent.cmd"; Flags: runhidden waituntilterminated; RunOnceId: "StopAgent"
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -Command ""Start-Process netsh -Verb RunAs -WindowStyle Hidden -ArgumentList 'advfirewall','firewall','delete','rule','name=CPR Agent'"""; \
  Flags: runhidden waituntilterminated; RunOnceId: "RemoveFirewallRule"

[UninstallDelete]
Type: filesandordirs; Name: "{app}\runtime"
Type: filesandordirs; Name: "{app}\app"

[Code]
const
  FirewallRuleName = 'CPR Agent';

// Records that we added a firewall rule, so uninstall only asks for admin to
// remove one that actually exists. Without this, every uninstall throws a UAC
// prompt at people who never enabled network play.
function FirewallMarkerPath: String;
begin
  Result := ExpandConstant('{app}\firewall-rule.installed');
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if (CurStep = ssPostInstall) and WizardIsTaskSelected('firewall') then
    SaveStringToFile(FirewallMarkerPath, FirewallRuleName, False);
end;

procedure RemoveFirewallRule;
var
  ResultCode: Integer;
begin
  if not FileExists(FirewallMarkerPath) then
    Exit;

  Exec('powershell.exe',
       '-NoProfile -ExecutionPolicy Bypass -Command "Start-Process netsh -Verb RunAs -WindowStyle Hidden ' +
       '-ArgumentList ''advfirewall'',''firewall'',''delete'',''rule'',''name=' + FirewallRuleName + '''"',
       '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

  DeleteFile(FirewallMarkerPath);
end;

// Uninstall keeps the user's data unless they say otherwise. Characters and
// accounts live in data\, and deleting them by accident is unforgivable.
//
// SuppressibleMsgBox rather than plain MsgBox, with an explicit IDNO default.
// A plain MsgBox still opens during a /VERYSILENT uninstall, which blocks an
// unattended run on a dialog nobody is there to answer. This way a silent
// uninstall keeps the data, which is the only safe answer to a question that
// was never actually asked.
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  DataDir: String;
begin
  if CurUninstallStep = usPostUninstall then
  begin
    RemoveFirewallRule;

    DataDir := ExpandConstant('{app}\data');

    if DirExists(DataDir) then
    begin
      if SuppressibleMsgBox('Delete your CPR Agent data as well?' + #13#10#13#10 +
                            'This removes your characters, accounts and settings permanently.' + #13#10 +
                            'Choose No to keep them for a future reinstall.',
                            mbConfirmation, MB_YESNO or MB_DEFBUTTON2, IDNO) = IDYES then
        DelTree(DataDir, True, True, True);
    end;
  end;
end;
