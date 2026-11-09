# Deploying Secure Task Management System to Red Hat OpenShift

This guide walks you step-by-step through deploying the **Secure Task Management System** on **Red Hat OpenShift** and explains how it connects to or replaces your local **XAMPP** environment.

---

## 1. XAMPP vs. Red Hat OpenShift: What Changes?

| Feature | Local Development (XAMPP) | Cloud Production (Red Hat OpenShift) |
| :--- | :--- | :--- |
| **Architecture** | Monolithic local server (All services running on host PC) | Microservices running in isolated Linux containers (Pods) |
| **Web Server** | Apache (`httpd.exe`) on port 80 / 443 | Containerized Apache (`php:8.2-apache` / UBI) on port 8080 |
| **Database** | Local MySQL / MariaDB on `localhost:3306` | Cloud MySQL Pod (`task-manager-mysql:3306`) backed by PersistentVolumeClaim |
| **Security Context** | Runs as current Windows/Admin user | Runs under OpenShift **SCC** as an arbitrary non-root UID |
| **Access URL** | `http://localhost/NormalProject/` | Secure OpenShift Route: `https://secure-task-manager-<project>.apps.<cluster>/` |

---

## 2. Moving Your Database from XAMPP to OpenShift

If you already created tasks or users in your local XAMPP environment:

1. Open your browser and go to your local phpMyAdmin: `http://localhost/phpmyadmin/`.
2. Select the `secure_task_manager` database on the left sidebar.
3. Click the **Export** tab at the top.
4. Select **Quick - display only the minimal options** and format **SQL**, then click **Export** (this downloads `secure_task_manager.sql`).
5. If you do not have custom data to migrate, you can simply use the included `database/database.sql` file!

---

## 3. Deployment Method A: Using OpenShift CLI (`oc`) (Recommended)

### Prerequisites:
- OpenShift CLI (`oc`) installed.
- Logged into your Red Hat OpenShift cluster (`oc login --token=... --server=https://api.your-cluster:6443`).

### Step 1: Create a Project (Namespace)
```bash
oc new-project task-manager
```

### Step 2: Deploy the Database Secret & Persistent Storage
```bash
oc apply -f openshift/secret.yaml
oc apply -f openshift/mysql.yaml
```

Wait until the MySQL pod is running:
```bash
oc get pods -w
```
*(Press `Ctrl + C` once the status is `Running`)*

### Step 3: Build & Deploy the Application

#### Option 3A: Build directly in OpenShift from your Git repository:
If you pushed your repository to GitHub / GitLab:
```bash
# 1. Update the git URL in openshift/buildconfig.yaml with your GitHub repository URL
oc apply -f openshift/buildconfig.yaml

# 2. Trigger the OpenShift build
oc start-build secure-task-manager --follow

# 3. Deploy the application service & route
oc apply -f openshift/app.yaml
oc apply -f openshift/route.yaml
```

#### Option 3B: Quick Source-to-Image (S2I) (No Dockerfile needed):
Red Hat OpenShift has built-in PHP builders:
```bash
oc new-app php:8.2~https://github.com/<YOUR-USERNAME>/<YOUR-REPO-NAME>.git \
  --name=secure-task-manager \
  --env=DB_HOST=task-manager-mysql \
  --env=DB_PORT=3306 \
  --env=DB_NAME=secure_task_manager \
  --env=DB_USER=task_user \
  --env=DB_PASS=Password123!

oc expose svc/secure-task-manager
```

#### Option 3C: Using your own prebuilt image (from Docker Hub / Quay.io):
```bash
docker build -t quay.io/<your-username>/secure-task-manager:latest .
docker push quay.io/<your-username>/secure-task-manager:latest

# Update image in openshift/app.yaml, then:
oc apply -f openshift/app.yaml
oc apply -f openshift/route.yaml
```

### Step 4: Initialize the Database Schema

#### Via Browser (One-Click Auto Setup):
1. Get your application's public route URL:
   ```bash
   oc get route secure-task-manager
   ```
2. Open the URL in your browser: `https://<YOUR-ROUTE-HOST>/setup_database.php`.
3. You will see green confirmation checkmarks indicating the database and demo accounts were initialized.

#### Or via OpenShift Pod Terminal:
```bash
# Find the PHP pod name
oc get pods -l component=web

# Run setup command inside the container
oc exec -it deployment/secure-task-manager -- php setup_database.php
```

---

## 4. Deployment Method B: OpenShift Web Console (UI)

If you prefer using the browser OpenShift Console:

1. **Log in** to your OpenShift Web Console.
2. Switch to the **Developer** perspective in the top left dropdown.
3. Click **+Add** &rarr; **Project** &rarr; Name it `task-manager`.
4. **Deploy MySQL Database**:
   - In **+Add**, select **Database** &rarr; choose **MySQL (Persistent)**.
   - Enter:
     - Database Service Name: `task-manager-mysql`
     - MySQL Database Name: `secure_task_manager`
     - MySQL Connection Username: `task_user`
     - MySQL Connection Password: `Password123!`
     - MySQL Root Password: `RootPassword123!`
   - Click **Create**.
5. **Deploy the PHP Application**:
   - In **+Add**, select **Import from Git**.
   - Paste your GitHub / GitLab repository URL.
   - OpenShift will auto-detect **PHP** (or select **PHP 8.2** / **Dockerfile**).
   - Under **Deployment**, ensure **Deployment** is selected.
   - Under **Routing**, check **Create a route to the Application** (Target port `8080`).
   - Click **Deployment** &rarr; **Environment variables** &rarr; add:
     - `DB_HOST` = `task-manager-mysql`
     - `DB_PORT` = `3306`
     - `DB_NAME` = `secure_task_manager`
     - `DB_USER` = `task_user`
     - `DB_PASS` = `Password123!`
   - Click **Create**.
6. **Initialize Database**:
   - Once the build and deployment finish, click the Open URL icon on the `secure-task-manager` topology node.
   - Append `/setup_database.php` to the URL in your address bar (e.g. `https://secure-task-manager-task-manager.apps.../setup_database.php`).
   - Your database tables and demo seed accounts are created!

---

## 5. Security & OpenShift Constraints (SCC) Explained

Why this application is 100% compliant with Red Hat OpenShift:
1. **Non-Root Execution:** OpenShift forbids containers from running as root by default (`restricted` SCC). The provided `Dockerfile` runs as non-root user `1001` with group ownership `root` (GID `0`) according to official Red Hat guidelines.
2. **Port 8080:** Non-root users cannot bind to privileged ports below 1024. Apache is reconfigured to listen on port `8080`.
3. **Environment Decoupling:** Database credentials are never committed into git; they are injected via Kubernetes Secrets and environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
4. **Edge TLS Termination:** The OpenShift Route terminates HTTPS/TLS at the OpenShift HAProxy router, securing traffic over public networks automatically.

---

## 6. Default Demo Credentials

Once deployed and initialized:
- **Demo User:** `demo@securetask.local` / `Password123!`
- **Administrator:** `admin@securetask.local` / `Password123!`

