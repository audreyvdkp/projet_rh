import joblib
import pandas as pd

# Charger le modèle sauvegardé
modele = joblib.load('modele_compatibilite.pkl')

# ============================================
# DONNÉES D'ENTRÉE (ce que l'utilisateur saisit)
# ============================================

# Exigences du poste
competences_requises = "Python; Machine Learning; SQL; Django"
experience_requise = 3  # années
diplome_requis = "Master"
langues_requises = "Français (courant); Anglais (avancé)"

# Profil du candidat
competences_candidat = "Python; Machine Learning; TensorFlow; SQL; Git"
experience_candidat = 4  # années
diplome_candidat = "Master"
langues_candidat = "Français (courant); Anglais (intermédiaire); Espagnol (débutant)"

# Pondérations définies par le recruteur (doivent totaliser 100%)
poids_competences = 0.60
poids_experience = 0.25
poids_diplome = 0.10
poids_langues = 0.05

# ============================================
# CALCUL AUTOMATIQUE DES TAUX
# ============================================

# 1. Taux de compétences (comptage simple pour l'instant)
comp_requises_list = [c.strip() for c in competences_requises.split(";")]
comp_candidat_list = [c.strip() for c in competences_candidat.split(";")]
correspondances = sum(1 for comp in comp_requises_list if comp in comp_candidat_list)
taux_competences = correspondances / len(comp_requises_list) if len(comp_requises_list) > 0 else 0

# 2. Taux d'expérience
taux_experience = min(experience_candidat / experience_requise, 1.0) if experience_requise > 0 else 1.0

# 3. Taux de diplôme
echelle_diplomes = {"Bac": 1, "Licence": 2, "Master": 3}
niveau_requis = echelle_diplomes.get(diplome_requis, 1)
niveau_candidat = echelle_diplomes.get(diplome_candidat, 1)
if niveau_candidat >= niveau_requis:
    taux_diplome = min(1.2, niveau_candidat / niveau_requis)  # Bonus max 1.2
else:
    taux_diplome = niveau_candidat / niveau_requis

# 4. Taux de langues
langues_req_list = [l.strip() for l in langues_requises.split(";")]
echelle_langues = {"débutant": 1, "intermédiaire": 2, "avancé": 3, "courant": 4}

taux_langues_total = 0
for langue_req in langues_req_list:
    nom_langue, niveau_req = langue_req.split(" (")
    niveau_req = niveau_req.rstrip(")")
    niveau_req_num = echelle_langues.get(niveau_req, 1)
    
    # Chercher cette langue chez le candidat
    niveau_cand_num = 0
    for langue_cand in langues_candidat.split(";"):
        langue_cand = langue_cand.strip()
        if nom_langue in langue_cand:
            _, niveau_cand = langue_cand.split(" (")
            niveau_cand = niveau_cand.rstrip(")")
            niveau_cand_num = echelle_langues.get(niveau_cand, 0)
            break
    
    if niveau_cand_num >= niveau_req_num:
        taux_langues_total += 1
    else:
        taux_langues_total += niveau_cand_num / niveau_req_num if niveau_req_num > 0 else 0

taux_langues = taux_langues_total / len(langues_req_list) if len(langues_req_list) > 0 else 0

# ============================================
# PRÉDICTION DU MODÈLE
# ============================================

nouvelle_candidature = pd.DataFrame({
    'taux_competences': [taux_competences],
    'taux_experience': [taux_experience],
    'taux_diplome': [taux_diplome],
    'taux_langues': [taux_langues],
    'poids_competences': [poids_competences],
    'poids_experience': [poids_experience],
    'poids_diplome': [poids_diplome],
    'poids_langues': [poids_langues]
})

score_predicte = modele.predict(nouvelle_candidature)

# ============================================
# AFFICHAGE DES RÉSULTATS
# ============================================

print("=" * 60)
print("🎯 SYSTÈME INTELLIGENT DE RECRUTEMENT - PRÉDICTION IA")
print("=" * 60)

print("\n📋 EXIGENCES DU POSTE :")
print(f"   Compétences : {competences_requises}")
print(f"   Expérience : {experience_requise} ans")
print(f"   Diplôme : {diplome_requis}")
print(f"   Langues : {langues_requises}")

print("\n👤 PROFIL DU CANDIDAT :")
print(f"   Compétences : {competences_candidat}")
print(f"   Expérience : {experience_candidat} ans")
print(f"   Diplôme : {diplome_candidat}")
print(f"   Langues : {langues_candidat}")

print("\n⚖️ PONDÉRATIONS DU RECRUTEUR :")
print(f"   Compétences : {poids_competences*100:.0f}%")
print(f"   Expérience : {poids_experience*100:.0f}%")
print(f"   Diplôme : {poids_diplome*100:.0f}%")
print(f"   Langues : {poids_langues*100:.0f}%")

print("\n📊 ANALYSE AUTOMATIQUE :")
print(f"   Taux de compétences : {taux_competences*100:.1f}%")
print(f"   Taux d'expérience : {taux_experience*100:.1f}%")
print(f"   Taux de diplôme : {taux_diplome*100:.1f}%")
print(f"   Taux de langues : {taux_langues*100:.1f}%")

print("\n" + "=" * 60)
print(f"🏆 SCORE DE COMPATIBILITÉ PRÉDIT : {score_predicte[0]:.2f} %")
print("=" * 60)