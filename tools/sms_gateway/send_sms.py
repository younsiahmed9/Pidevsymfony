#!/usr/bin/env python3
"""
Script Python pour envoyer des SMS gratuitement via diverses gateways
"""

import requests
import sys
import json

def send_via_textbelt(phone, message):
    """TextBelt - 100 SMS gratuits par mois"""
    try:
        response = requests.post('https://textbelt.com/text', {
            'phone': phone,
            'message': message,
            'key': 'textbelt'
        })
        data = response.json()
        return data.get('success', False)
    except:
        return False

def send_via_free_sms_api(phone, message):
    """Free SMS API - service gratuit"""
    try:
        response = requests.get('https://smsapi.free-mobile.fr/sendmsg', {
            'user': 'your_user',
            'pass': 'your_pass',
            'msg': f"{phone}: {message}"
        })
        return response.status_code == 200
    except:
        return False

def send_via_callmebot(phone, message):
    """CallMeBot - service gratuit pour certains pays"""
    try:
        response = requests.get('https://api.callmebot.com/sms/send', {
            'phone': phone,
            'message': message,
            'apikey': 'your_api_key'
        })
        return response.status_code == 200
    except:
        return False

def main():
    if len(sys.argv) != 3:
        print("Usage: python send_sms.py <phone> <message>")
        sys.exit(1)
    
    phone = sys.argv[1]
    message = sys.argv[2]
    
    # Essayer différentes méthodes gratuites
    methods = [
        send_via_textbelt,
        send_via_free_sms_api,
        send_via_callmebot
    ]
    
    for method in methods:
        if method(phone, message):
            print(f"SMS sent successfully via {method.__name__}")
            sys.exit(0)
    
    print("Failed to send SMS via all methods")
    sys.exit(1)

if __name__ == "__main__":
    main()
