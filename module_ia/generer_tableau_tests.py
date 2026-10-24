import joblib
import pandas as pd

# 1. Charger le modèle sauvegardé
modele = joblib.load('modele_compatibilite.pkl')

# 2. Définir les scénarios de test
# On garde les mêmes pondérations pour tous les tests afin de bien comparer les profils
# (Compétences: 60%, Expérience: 25%, Diplôme: 10%, Langues: 5%)
scenarios = [
    {
        "Nom": "Candidat idéal",
        "taux_competences": 0.90,
        "taux_experience": 1.00,
        "taux_diplome": 1.20,  # Bonus diplôme supérieur
        "taux_langues": 0.80
    },
    {
        "Nom": "Candidat confirmé",
        "taux_competences": 0.80,
        "taux_experience": 1.00,
        "taux_diplome": 1.00,
        "taux_langues": 0.75
    },
    {
        "Nom": "Candidat moyen",
        "taux_competences": 0.60,
        "taux_experience": 0.50,
        "taux_diplome": 1.00,
        "taux_langues": 0.40
    },
    {
        "Nom": "Candidat junior",
        "taux_competences": 0.40,
        "taux_experience": 0.30,
        "taux_diplome": 1.00,  # Bon diplôme mais peu d'expérience
        "taux_langues": 0.60
    },
    {
        "Nom": "Candidat inadapté",
        "taux_competences": 0.20,
        "taux_experience": 0.20,
        "taux_diplome": 0.50,
        "taux_langues": 0.30
    }
]

# Poids fixes pour la comparaison (total = 1.0)
poids = {
    "poids_competences": 0.60,
    "poids_experience": 0.25,
    "poids_diplome": 0.10,
    "poids_langues": 0.05
}

# 3. Exécuter les prédictions et stocker les résultats
resultats = []

for scen in scenarios:
    # Créer un DataFrame avec une seule ligne pour la prédiction
    data = {
        "taux_competences": [scen["taux_competences"]],
        "taux_experience": [scen["taux_experience"]],
        "taux_diplome": [scen["taux_diplome"]],
        "taux_langues": [scen["taux_langues"]],
        "poids_competences": [poids["poids_competences"]],
        "poids_experience": [poids["poids_experience"]],
        "poids_diplome": [poids["poids_diplome"]],
        "poids_langues": [poids["poids_langues"]]
    }
    
    df_test = pd.DataFrame(data)
    
    # Prédire le score
    score_predicte = modele.predict(df_test)[0]
    
    # Ajouter au tableau de résultats
    resultats.append({
        "Scénario": scen["Nom"],
        "Taux Comp.": f"{scen['taux_competences']*100:.0f}%",
        "Taux Exp.": f"{scen['taux_experience']*100:.0f}%",
        "Taux Dipl.": f"{scen['taux_diplome']*100:.0f}%",
        "Taux Lang.": f"{scen['taux_langues']*100:.0f}%",
        "Score Prédit (IA)": f"{score_predicte:.2f}%"
    })

# 4. Afficher le tableau proprement
df_resultats = pd.DataFrame(resultats)

print("=" * 85)
print("📊 RÉSULTATS DES TESTS DE PRÉDICTION DU MODÈLE D'IA")
print("⚖️ Pondérations appliquées : Compétences 60% | Expérience 25% | Diplôme 10% | Langues 5%")
print("=" * 85)
print(df_resultats.to_string(index=False))
print("=" * 85)