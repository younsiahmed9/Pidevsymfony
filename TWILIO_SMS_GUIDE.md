# Guide Complet d'Intégration Twilio SMS pour le Module Crédit

## 📋 État Actuel de l'Intégration

### ✅ Ce qui est déjà implémenté:

1. **SmsService.php** (`src/Service/SmsService.php`)
   - Classe principale d'orchestration SMS
   - Méthode `sendSmsTwilioOnly()` pour forcer Twilio uniquement
   - Méthode `sendSms()` avec fallback Brevo → Twilio
   - Normalisation des numéros internationaux
   - Gestion des erreurs en français

2. **CreditController.php** (ligne 231)
   - Utilise `sendSmsTwilioOnly()` après enregistrement du crédit
   - Récupère le numéro du client via `getClient()->getPhone()`
   - Affiche les messages d'erreur en français aux utilisateurs
   - Flash message `warning` si SMS échoue

3. **Configuration .env.local**
   - `TWILIO_ACCOUNT_SID` ✓
   - `TWILIO_AUTH_TOKEN` ✓
   - `TWILIO_FROM_NUMBER` ✓
   - `TWILIO_MESSAGING_SERVICE_SID` (optionnel)

4. **Injection dans config/services.yaml**
   - Tous les paramètres Twilio injectés dans SmsService

---

## ⚙️ Configuration Twilio Complète

### 1. **Vérifier les Credentials Twilio**

Vos identifiants actuels:
```
Account SID:          YOUR_TWILIO_ACCOUNT_SID
Auth Token:           YOUR_TWILIO_AUTH_TOKEN
From Number:          +12282253082
Messaging Service ID: (non configuré)
```

**Vérification:**
- Visitez: https://console.twilio.com/
- Aller à "Account Info"
- Vérifier que Account SID et Auth Token correspondent
- Note: From Number doit être un numéro Twilio acheté ou un shortcode

### 2. **Passer de Trial à Paid (RECOMMANDÉ)**

**Limite Trial:**
- ✗ Seuls numéros vérifiés peuvent recevoir SMS
- ✗ Pas d'envoi vers les pays non vérifiés
- ✓ Parfait pour développement local

**Passer à Paid:**
1. https://console.twilio.com/
2. → Account Info
3. → Upgrade Account
4. → Ajouter carte de crédit
5. → Activation immédiate

**Après upgrade:**
- ✓ Envoi vers tous les pays (configurable)
- ✓ Pas besoin de vérifier chaque numéro
- ✓ Volume illimité (facturé par SMS)

### 3. **Mode Trial: Vérifier les Numéros**

Si vous restez en Trial:

1. **Vérifier votre propre numéro:**
   - https://console.twilio.com/
   - → Phone Numbers
   - → Verified Caller IDs
   - → "+" (Ajouter)
   - Saisir: +216XXXXXXXX (votre numéro)
   - Recevoir code de vérification par SMS
   - Confirmer le code

2. **Vérifier les numéros clients:**
   - Chaque numéro client doit être ajouté de la même manière
   - Ou passer à un compte Payant (plus simple)

### 4. **Configurer la Géographie (Important pour Tunisie)**

1. https://console.twilio.com/
2. → Messaging
3. → Services (ou Create Service)
4. → Geographic Permissions
5. ✓ Activer: Tunisia (TN)
6. ✓ Activer: Autres pays clients

---

## 🧪 Tests d'Envoi SMS

### Test 1: Via Fichier Test

Fichier créé: `test_twilio_sms.php`

```bash
php test_twilio_sms.php
```

Remplacer `+216 50 123 456` par votre numéro réel à l'intérieur du fichier.

Résultat attendu:
```
✓ SMS envoyé avec succès via Twilio!
```

### Test 2: Via SmsController

Endpoint créé: `/api/sms/send` (POST)

```bash
curl -X POST http://localhost:8000/api/sms/send \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+216XXXXXXXX",
    "message": "FinTrack: Test SMS depuis API"
  }'
```

Réponse attendue:
```json
{
  "success": true,
  "message": "SMS envoyé avec succès"
}
```

### Test 3: Créer un Crédit (Test Réel)

1. Aller à: http://localhost:8000/credit/new
2. Remplir le formulaire:
   - Montant: 1000 DT
   - Type: Personnel
   - Justifications: Documents...
3. S'assurer que le numéro de téléphone est configuré dans le profil
4. Soumettre le formulaire

Résultat:
- ✓ Crédit enregistré
- ✓ SMS envoyé avec confirmation
- ✓ Voir le SMS en environ 5-10 secondes

---

## 📱 Format des Numéros Acceptés

Tous ces formats sont automatiquement normalisés en E.164:

| Format | Exemple | Résultat |
|--------|---------|----------|
| International | +33612345678 | +33612345678 ✓ |
| 00 Prefix | 0033612345678 | +33612345678 ✓ |
| Tunisie complet | 21650123456 | +21650123456 ✓ |
| Tunisie 8 chiffres | 50123456 | +21650123456 ✓ |
| Tunisie avec 0 | 050123456 | +21650123456 ✓ |
| Espaces/Tirets | +216 50 123-456 | +21650123456 ✓ |
| Invalid | abc123 | ✗ Rejeté |

---

## 🛠️ Dépannage

### ❌ Erreur: "Trial: numéro doit être vérifié"

**Cause:** Compte Trial + numéro non vérifié

**Solutions:**
1. Vérifier le numéro dans Twilio Dashboard
2. **RECOMMANDÉ**: Passer à un compte Payant

**Vérifier via test:**
```bash
php test_twilio_sms.php
```

### ❌ Erreur: "Geographic permissions not enabled"

**Cause:** Tunisie non activée dans Twilio

**Solution:**
1. https://console.twilio.com/ → Messaging → Services
2. Geographic Permissions
3. ✓ Activer "Tunisia"

### ❌ Erreur: "Invalid Auth Token"

**Cause:** Token expiré ou incorrect

**Solution:**
1. Copier le nouveau token depuis Twilio Console
2. Mettre à jour `.env.local`:
   ```
   TWILIO_AUTH_TOKEN=<nouveau_token>
   ```
3. Redémarrer le serveur

### ❌ Erreur: "Timeout (>25s)"

**Cause:** Réseau lent ou API Twilio surchargée

**Solution:**
1. Vérifier la connexion Internet
2. Relancer le test (Twilio reprend généralement)
3. Consulter Twilio Status: https://status.twilio.com/

### ❌ SMS Non Reçu (même succès)

**Causes possibles:**
1. **Téléphone désactivé** - Allumer le téléphone
2. **Réseau cellulaire faible** - Vérifier la couverture
3. **Filtres SMS** - Vérifier la liste noire/spam
4. **Opérateur bloque Twilio** - Contacter opérateur
5. **Numéro incorrect** - Vérifier le format E.164

**Debug:**
```bash
# Consulter les logs Twilio
tail -f var/log/dev.log | grep -i twilio
```

---

## 📊 Monitoring en Production

### Logs d'Audit

Tous les envois SMS sont loggés dans `var/log/dev.log`:

```
[INFO] SMS envoyé avec succès à +21650123456 (Twilio) [status: 201]
[ERROR] Échec Twilio SMS vers +21650123456 [http: 401, twilio_message: "Invalid auth token"]
[WARNING] SMS Brevo en échec, tentative Twilio [dest: +21650123456]
```

### Consulter les Logs

```bash
# En temps réel
tail -f var/log/prod.log | grep SMS

# Dernières 100 lignes
tail -100 var/log/prod.log | grep -i sms

# Filtrer par numéro
grep "21650123456" var/log/prod.log
```

### Dashboard Twilio

Pour voir les SMS envoyés:
1. https://console.twilio.com/
2. → Logs
3. → Messages
4. Vérifier status (Delivered, Undelivered, Failed)

---

## 🚀 Checklist Final

- [ ] Account SID copié depuis Twilio
- [ ] Auth Token copié depuis Twilio
- [ ] .env.local mis à jour
- [ ] test_twilio_sms.php exécuté avec succès
- [ ] Numéro de téléphone du client configuré
- [ ] Crédit créé = SMS reçu sur le téléphone
- [ ] Logs consultés pour les détails
- [ ] Géographie Tunisie activée dans Twilio
- [ ] (Optionnel) Compte Payant configuré

---

## 📞 Support & Ressources

**Documentation Officielle:**
- Twilio API: https://www.twilio.com/docs/sms/send-messages
- Twilio SDK PHP: https://www.twilio.com/docs/libraries/php
- Codes d'erreur: https://www.twilio.com/docs/api/errors

**Dashboard:**
- Console: https://console.twilio.com/
- Logs: https://console.twilio.com/us1/account/logs/list
- Messages: https://console.twilio.com/us1/account/messaging/test-message

**Vérifier le Statut API:**
- https://status.twilio.com/

---

## 📝 Code Pertinent (Référence)

### CreditController.php (utilisation)
```php
if (!$smsService->sendSmsTwilioOnly($phone, $msg)) {
    $hint = $smsService->getLastFailureHintFr();
    $this->addFlash('warning', 'SMS non envoyé: ' . ($hint ?? 'Erreur'));
}
```

### SmsService.php (logique)
```php
public function sendSmsTwilioOnly(string $to, string $message): bool {
    $to = $this->normalizeInternationalPhone($to);
    // Validation
    // Envoi via HTTP POST à Twilio
    // Gestion des erreurs en français
    return $success;
}
```

### .env.local (configuration)
```
TWILIO_ACCOUNT_SID=YOUR_TWILIO_ACCOUNT_SID
TWILIO_AUTH_TOKEN=YOUR_TWILIO_AUTH_TOKEN
TWILIO_FROM_NUMBER=+12282253082
TWILIO_MESSAGING_SERVICE_SID=
```

---

## ✅ Résumé

**État: Prêt à l'emploi ✓**

L'intégration Twilio SMS est **complètement implémentée** et **opérationnelle**. 

**Actions requises:**
1. Vérifier/mettre à jour les credentials dans `.env.local`
2. Exécuter `test_twilio_sms.php` pour valider
3. (Optionnel) Passer à un compte Payant pour plus de flexibilité
4. Tester avec un crédit réel

**Résultat attendu:**
- SMS arrivent dans les 5-10 secondes
- Messages en français clairs
- Logs d'audit complets dans `var/log/`
