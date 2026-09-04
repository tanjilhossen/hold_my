# 🚀 Taqamul Engine VPS Deployment Guide (Port 7777)

## 📌 VPS Information
- **IP Address**: `200.234.41.119`
- **Hostname**: `srv1923284.hstgr.cloud`
- **OS**: Ubuntu 24.04 LTS (Hostinger KVM 2)
- **Deployment Port**: `7777`
- **SSH Command**: `ssh root@200.234.41.119`

---

## ⚡ 1-Step Automated Deployment Command
VPS এ লগইন করে টার্মিনালে শুধু নিচের সিঙ্গেল কমান্ডটি রান করুন:

```bash
curl -sSL https://raw.githubusercontent.com/zidanmahmudxz/hold_slot/main/vps_deploy.sh | bash
```

---

## 🛠️ Manual Step-by-Step Instructions (Alternative)

যদি ম্যানুয়ালি ইনস্টল করতে চান:

```bash
# 1. Clone repository into /var/www/taqamul
git clone https://github.com/zidanmahmudxz/hold_slot.git /var/www/taqamul
cd /var/www/taqamul

# 2. Make deployment script executable & run
chmod +x vps_deploy.sh
./vps_deploy.sh
```

---

## 🔐 Login Access & Dashboard Details
- **Website URL**: [http://200.234.41.119:7777](http://200.234.41.119:7777)
- **Super Admin Credentials**:
  - **Email**: `admin@taqamul.com`
  - **Password**: `admin123`
- **Agency Staff Credentials**:
  - **Email**: `user@taqamul.com`
  - **Password**: `user123`

---

## ⚙️ Service Control Commands (Systemd)
- **Check Status**: `systemctl status taqamul`
- **Restart Service**: `systemctl restart taqamul`
- **View Live Logs**: `journalctl -u taqamul -f`
