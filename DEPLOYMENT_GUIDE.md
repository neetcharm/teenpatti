# 🚀 VPS Deployment Guide (Production)

This guide walks you through deploying your Teen Patti Game to a VPS (Contabo, DigitalOcean, etc.) with a custom domain and SSL.

## 📋 Prerequisites
- A VPS running **Ubuntu 22.04+** (Recommended).
- A Domain Name (e.g., `game.yourdomain.com`).
- Root access to your server.

---

## 🏗️ Step 1: Server Preparation

Connect to your VPS via SSH and install Docker:

```bash
# Update System
sudo apt update && sudo apt upgrade -y

# Install Docker & Compose
sudo apt install -y docker.io docker-compose nginx certbot python3-certbot-nginx

# Install Portainer (GUI for Docker)
sudo docker volume create portainer_data
sudo docker run -d -p 9443:9443 --name portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer-ce:latest
```

---

## 🎯 Step 2: Point Your Domain

1. Go to your Domain Provider (GoDaddy, Namecheap, etc.).
2. Add an **A Record**:
   - **Host**: `game` (or `@` for root domain)
   - **Value**: `YOUR_VPS_IP_ADDRESS`
   - **TTL**: `600` (or default)

---

## 📂 Step 3: Deploy the Code

1. **Clone your repository** to the VPS:
   ```bash
   git clone https://github.com/saxena-deepanshu/teenpatti.git /var/www/teenpatti
   cd /var/www/teenpatti
   ```

2. **Setup Production .env**:
   ```bash
   cp core/.env.example core/.env
   nano core/.env
   ```
   **Update these critical values:**
   - `APP_DEBUG=false`
   - `APP_URL=https://game.tikkix.com`
   - `DB_HOST=games`
   - `DB_PASSWORD=TikkiX_P9wR2mK_Secure_2026!#`
   - `DB_DATABASE=games`

3. **Update docker-compose.yml**:
   Ensure the `MYSQL_ROOT_PASSWORD` in `docker-compose.yml` is also set to `TikkiX_P9wR2mK_Secure_2026!#`.

---

## 🚀 Step 4: Start the Application

```bash
# Build and start in detached mode
docker-compose up -d --build

# Import your latest backup
docker exec -i games_db mysql -u root -pTikkiX_P9wR2mK_Secure_2026!# games < install/backup_2026-04-21_01-24.sql

# Run migrations to ensure latest schema
docker exec -it teenpatti_app php core/artisan migrate --force
```

---

## 🔒 Step 5: Nginx Reverse Proxy & SSL

1. **Create Nginx Config**:
   ```bash
   sudo nano /etc/nginx/sites-available/teenpatti
   ```

2. **Paste this configuration** (Replace `game.tikkix.com` with your actual domain):
   ```nginx
   server {
       listen 80;
       server_name game.tikkix.com;

       location / {
           proxy_pass http://localhost:8000;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

3. **Enable and Restart Nginx**:
   ```bash
   sudo ln -s /etc/nginx/sites-available/teenpatti /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

4. **Install SSL with Let's Encrypt**:
   ```bash
   sudo certbot --nginx -d game.tikkix.com
   ```
   *(Select "2" to redirect all HTTP traffic to HTTPS)*.

---

## 🏁 Step 6: Final Hardening

1. **Firewall**: Ensure only ports 80, 443, 9443 (Portainer), and 22 (SSH) are open.
   ```bash
   sudo ufw allow 'Nginx Full'
   sudo ufw allow 9443/tcp
   sudo ufw allow OpenSSH
   sudo ufw enable
   ```

2. **Admin Password**: Don't forget to reset your admin password using the `php artisan tinker` method we used locally!

---

## ✅ You are LIVE!
Your game is now accessible at **`https://game.tikkix.com`**.
- **Admin Panel**: `https://game.tikkix.com/admin`
- **Tenant API**: `https://game.tikkix.com/api/v1/...`
- **Portainer GUI**: `https://game.tikkix.com:9443` (Use your server IP if DNS isn't ready)
