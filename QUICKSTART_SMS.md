# ⚡ SMS Twilio - Démarrage Rapide (5 min)

**Vous êtes pressé?** Voici les 3 étapes essentielles pour avoir SMS Twilio en marche.

---

## 🚀 Étape 1: Tester (2 min)

```bash
cd c:\xampp\htdocs\integration_api\integrationapi\integrationaetg
php test_twilio_sms.php
```

**Attendu:** ✓ SMS envoyé avec succès via Twilio!

---

## 🔧 Étape 2: Configurer Twilio (2 min)

Aller à: https://console.twilio.com/

**Checker 2 choses:**

1. **Geographic Permissions** (pour Tunisie)
   - Messaging → Services → Geographic Permissions
   - ✓ Activer "Tunisia"

2. **En Trial?** (compte gratuit)
   - Phone Numbers → Verified Caller IDs
   - Ajouter votre numéro
   - Recevoir code SMS
   - Confirmer

**En Payant?** (plus simple)
   - Account Info → Upgrade Account
   - Ajouter carte crédit
   - Zéro restriction ✓

---

## ✅ Étape 3: Tester Avec un Crédit (1 min)

1. Aller à: http://localhost:8000/credit/new
2. Créer un crédit
3. Vérifier numéro de téléphone ✓
4. Soumettre
5. **SMS reçu en 5-10 secondes!**

---

## ❌ Ça ne marche pas?

| Problème | Solution |
|----------|----------|
| "SMS non reçu" | Vérifier Verified Caller IDs dans Twilio |
| "Trial account" | Ajouter numéro dans Verified Caller IDs |
| "Timeout" | Vérifier connexion Internet |
| "Erreur config" | Vérifier .env.local a TWILIO_ACCOUNT_SID |

**Logs:**
```bash
tail -20 var/log/dev.log | grep -i twilio
```

---

## 📚 Besoin de plus de détails?

- **Vue d'ensemble:** TWILIO_SMS_SUMMARY.md
- **Guide complet:** TWILIO_SMS_GUIDE.md
- **Multi-modules:** SYNCHRONIZATION_SMS_TWILIO.md
- **Tous les détails:** README_SMS_TWILIO.md

---

**C'est tout! SMS Twilio fonctionne maintenant.** ✓
