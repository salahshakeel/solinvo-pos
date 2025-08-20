const { app, BrowserWindow } = require("electron");
const { exec } = require("child_process");
let server;

function createWindow() {
  // Start Laravel server with PHP built-in server
  server = exec("php -S 127.0.0.1:8000 -t public", {
    cwd: "F:/GithubProjects/solinvo-pos" // change to your Laravel folder
  });

  const win = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      nodeIntegration: false
    }
  });

  win.loadURL("http://127.0.0.1:8000");
}

app.on("ready", createWindow);

app.on("window-all-closed", () => {
  if (process.platform !== "darwin") {
    app.quit();
  }
});

app.on("quit", () => {
  if (server) server.kill();
});
