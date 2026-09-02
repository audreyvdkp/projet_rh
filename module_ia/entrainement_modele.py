import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, r2_score

# Charger le dataset préparé
dataset = pd.read_csv("dataset_prepare.csv")

# Variables utilisées par le modèle
X = dataset[
    [
        "taux_competences",
        "taux_experience",
        "taux_diplome",
        "taux_langues",
        "poids_competences",
        "poids_experience",
        "poids_diplome",
        "poids_langues"
    ]
]

# Score que le modèle doit apprendre à prédire
y = dataset["score_compatibilite"]

# Séparer les données : 80 % pour l'entraînement et 20 % pour le test
X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.2,
    random_state=42
)

# Créer le modèle
modele = RandomForestRegressor(
    n_estimators=100,
    random_state=42
)

# Entraîner le modèle
modele.fit(X_train, y_train)

# Faire les prédictions
predictions = modele.predict(X_test)

# Évaluer le modèle
mae = mean_absolute_error(y_test, predictions)
r2 = r2_score(y_test, predictions)

print("Entraînement terminé !")
print(f"Erreur moyenne absolue : {mae:.2f}")
print(f"Score R² : {r2:.4f}")

import joblib

# Sauvegarder le modèle entraîné
joblib.dump(modele, 'modele_compatibilite.pkl')

print("\n✅ Modèle sauvegardé avec succès sous le nom 'modele_compatibilite.pkl'")