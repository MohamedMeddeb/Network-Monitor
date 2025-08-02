import os
import platform
import subprocess
import time
import json
import smtplib
from email.message import EmailMessage
from datetime import datetime

def ping_ip(ip_address, attempts=3, threshold=2):
    failed = 0
    param = '-n' if platform.system().lower() == 'windows' else '-c'
    timeout = '-w' if platform.system().lower() == 'windows' else '-W'
    
    for _ in range(attempts):
        command = ['ping', param, '1', timeout, '1', ip_address]
        try:
            with open(os.devnull, 'w') as devnull:
                if subprocess.call(command, stdout=devnull, stderr=devnull) != 0:
                    failed += 1
        except Exception:
            failed += 1
        time.sleep(0.3)

    return failed < threshold

def send_email(to_email, ip):
    msg = EmailMessage()
    msg['Subject'] = f"Device Offline Alert: {ip}"
    msg['From'] = "" #Your smtp Network Monitor Gmail
    msg['To'] = to_email
    msg.set_content(f"The device with IP {ip} just went offline.")

    try:
        with smtplib.SMTP_SSL('smtp.gmail.com', 465) as smtp:
            smtp.login("", "") #Your smtp Network Monitor Gmail #The smtp Password
            smtp.send_message(msg)
    except Exception as e:
        print(f"Failed to send email: {e}")

# Load devices and user info
with open('devices.json', 'r') as f:
    devices = json.load(f)

with open('user_info.json', 'r') as f:
    user_info = json.load(f)

# Load and clean status cache
try:
    with open('status_cache.json', 'r') as f:
        status_cache = json.load(f)
        
    # Remove any cached IPs that are no longer in devices.json
    device_ips = [device['ip'] for device in devices]
    status_cache = {ip: status for ip, status in status_cache.items() if ip in device_ips}
except:
    status_cache = {}

updated_devices = []
for device in devices:
    ip = device['ip']
    current_status = 'Online' if ping_ip(ip) else 'Offline'
    
    if current_status == 'Online':
        device['last_online'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    if status_cache.get(ip) == 'Online' and current_status == 'Offline':
        send_email(user_info['email'], ip)
        if 'last_online' not in device:
            device['last_online'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    device['status'] = current_status
    status_cache[ip] = current_status
    updated_devices.append(device)

# Save updated data
with open('devices.json', 'w') as f:
    json.dump(updated_devices, f, indent=4)

with open('status_cache.json', 'w') as f:
    json.dump(status_cache, f, indent=4)

print(json.dumps({
    "timestamp": time.strftime('%Y-%m-%d %H:%M:%S'),
    "ips": {d['ip']: d['status'] for d in updated_devices}
}))