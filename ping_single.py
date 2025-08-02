#!/usr/bin/env python3
import os
import platform
import subprocess
import sys
import json
import time
from datetime import datetime

def ping_ip(ip_address, attempts=3, threshold=2):
    #Ping the IP multiple times. Return True if online, False if offline.
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
        time.sleep(0.3)  # short pause between attempts

    return failed < threshold

def update_status_in_file(ip, status):
    try:
        with open('devices.json', 'r') as f:
            devices = json.load(f)
    except Exception:
        return False

    updated = False
    for device in devices:
        if device.get('ip') == ip:
            # Update last_online if device came online
            if status == "Online":
                device['last_online'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            device['status'] = status
            updated = True
            break

    if updated:
        with open('devices.json', 'w') as f:
            json.dump(devices, f, indent=4)
    return updated

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({"error": "Invalid arguments"}))
        sys.exit(1)

    ip = sys.argv[1]
    status = "Online" if ping_ip(ip) else "Offline"
    updated = update_status_in_file(ip, status)
    
    # Get the updated device info to include last_online in response
    device_info = {}
    try:
        with open('devices.json', 'r') as f:
            devices = json.load(f)
            for device in devices:
                if device['ip'] == ip:
                    device_info = device
                    break
    except:
        pass

    result = {
        "ip": ip,
        "status": status,
        "last_online": device_info.get('last_online'),
        "valid": True,
        "updated": updated
    }

    print(json.dumps(result))