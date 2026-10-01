import sys
import joblib
import pandas as pd
import pdfplumber
import re
import os

# Charger le modèle
modele = joblib.load(os.path.join(os.path.dirname(__file__), 'modele_compatibilite.pkl'))

# ============================================
# FONCTIONS D'EXTRACTION
# ============================================

def extraire_texte_pdf(chemin_pdf):
    """Extrait le texte d'un PDF"""
    texte = ""
    try:
        with pdfplumber.open(chemin_pdf) as pdf:
            for page in pdf.pages:
                texte += page.extract_text() + "\n"
    except Exception as e:
        print(f"Erreur lecture PDF: {e}", file=sys.stderr)
    return texte

def extraire_experience(texte):
    """Extrait les années d'expérience"""
    # Pattern: "X ans d'expérience" ou "X années"
    patterns = [
        r'(\d+)\s*(?:ans|années|an)\s*(?:d\'|de)?\s*(?:expérience|exp)',
        r'(?:expérience|exp)[^0-9]{0,20}(\d+)\s*(?:ans|années|an)',
        r'(\d{4})\s*[-–]\s*(\d{4})',  # 2020-2024
    ]
    
    for pattern in patterns:
        match = re.search(pattern, texte, re.IGNORECASE)
        if match:
            if len(match.groups()) == 2:  # Période 2020-2024
                return int(match.group(2)) - int(match.group(1))
            else:
                return int(match.group(1))
    return 0

def extraire_diplome(texte):
    """Extrait le diplôme le plus élevé"""
    echelle = {"Doctorat": 4, "Master": 3, "Licence": 2, "DUT": 2, "BTS": 1, "Bac": 1}
    diplomes_trouves = []
    
    for diplome in echelle.keys():
        if re.search(r'\b' + diplome + r'\b', texte, re.IGNORECASE):
            diplomes_trouves.append((diplome, echelle[diplome]))
    
    if diplomes_trouves:
        return max(diplomes_trouves, key=lambda x: x[1])[0]
    return "Bac"

def extraire_langues(texte):
    """Extrait les langues et niveaux"""
    echelle_niveaux = {"courant": 4, "avancé": 3, "intermédiaire": 2, "débutant": 1}
    langues_trouvees = []
    
    # Pattern: "Français (courant)" ou "Anglais: avancé" ou "Anglais courant"
    patterns = [
        r'([A-Z][a-zéè]+)\s*[\(:]\s*([a-zéè]+)[\)]?',
        r'([A-Z][a-zéè]+)\s+(courant|avancé|intermédiaire|débutant)',
    ]
    
    langues_connues = ["Français", "Anglais", "Espagnol", "Allemand", "Arabe", "Portugais", "Chinois", "Italien"]
    
    for langue in langues_connues:
        if re.search(r'\b' + langue + r'\b', texte, re.IGNORECASE):
            niveau = 2  # Par défaut intermédiaire
            for pattern in patterns:
                matches = re.finditer(pattern, texte, re.IGNORECASE)
                for match in matches:
                    if langue.lower() in match.group(1).lower():
                        niveau_texte = match.group(2).lower()
                        if niveau_texte in echelle_niveaux:
                            niveau = echelle_niveaux[niveau_texte]
                            break
            langues_trouvees.append(f"{langue} ({list(echelle_niveaux.keys())[list(echelle_niveaux.values()).index(niveau)]})")
    
    return "; ".join(langues_trouvees) if langues_trouvees else "Français (courant)"

def extraire_competences(texte, competences_connues=None):
    """Extrait les compétences techniques"""
    if competences_connues is None:
        # Liste de compétences courantes à rechercher
        competences_connues = [
            "Python", "Java", "JavaScript", "C++", "C#", "PHP", "Ruby", "Go", "Rust",
            "SQL", "MySQL", "PostgreSQL", "MongoDB", "Redis",
            "HTML", "CSS", "React", "Angular", "Vue.js", "Node.js", "Django", "Flask",
            "TensorFlow", "PyTorch", "Scikit-learn", "Pandas", "NumPy",
            "Git", "Docker", "Kubernetes", "AWS", "Azure", "GCP",
            "Photoshop", "Illustrator", "InDesign", "Figma", "Canva",
            "Excel", "Word", "PowerPoint", "SAP", "Oracle",
            "Machine Learning", "Deep Learning", "Data Science", "IA",
            "Agile", "Scrum", "DevOps", "CI/CD",
            "Communication", "Leadership", "Gestion de projet", "Teamwork"
        ]
    
    competences_trouvees = []
    for comp in competences_connues:
        if re.search(r'\b' + re.escape(comp) + r'\b', texte, re.IGNORECASE):
            competences_trouvees.append(comp)
    
    return "; ".join(competences_trouvees)

def extraire_exigences_offre(description):
    """Extrait les exigences de la description de l'offre"""
    # Compétences
    competences = extraire_competences(description)
    
    # Expérience
    experience = extraire_experience(description)
    if experience == 0:
        # Chercher "X années d'expérience" ou "X ans d'expérience"
        match = re.search(r'(\d+)\s*(?:ans|années)\s*(?:d\'|de)?\s*(?:expérience|exp)', description, re.IGNORECASE)
        if match:
            experience = int(match.group(1))
    
    # Diplôme
    diplome = extraire_diplome(description)
    
    # Langues
    langues = extraire_langues(description)
    
    return {
        'competences': competences,
        'experience': experience,
        'diplome': diplome,
        'langues': langues
    }

# ============================================
# CALCUL DES TAUX
# ============================================

def calculer_taux(competences_req, exp_req, diplome_req, langues_req,
                  competences_cand, exp_cand, diplome_cand, langues_cand):
    
    # Taux compétences
    comp_req_list = [c.strip() for c in competences_req.split(";") if c.strip()]
    comp_cand_list = [c.strip() for c in competences_cand.split(";") if c.strip()]
    correspondances = sum(1 for comp in comp_req_list if comp.lower() in [c.lower() for c in comp_cand_list])
    taux_comp = correspondances / len(comp_req_list) if len(comp_req_list) > 0 else 0
    
    # Taux expérience
    taux_exp = min(exp_cand / exp_req, 1.0) if exp_req > 0 else 1.0
    
    # Taux diplôme
    echelle_diplomes = {"Bac": 1, "Licence": 2, "Master": 3, "Doctorat": 4, "BTS": 1, "DUT": 2}
    niveau_req = echelle_diplomes.get(diplome_req, 1)
    niveau_cand = echelle_diplomes.get(diplome_cand, 1)
    if niveau_cand >= niveau_req:
        taux_diplome = min(1.2, niveau_cand / niveau_req)
    else:
        taux_diplome = niveau_cand / niveau_req
    
    # Taux langues
    echelle_langues = {"débutant": 1, "intermédiaire": 2, "avancé": 3, "courant": 4}
    langues_req_list = [l.strip() for l in langues_req.split(";") if l.strip()]
    taux_langues_total = 0
    
    for langue_req in langues_req_list:
        if "(" in langue_req:
            nom_langue, niveau_req_texte = langue_req.split("(", 1)
            niveau_req_texte = niveau_req_texte.strip().rstrip(")").lower()
            niveau_req_num = echelle_langues.get(niveau_req_texte, 2)
        else:
            nom_langue = langue_req.strip()
            niveau_req_num = 2
        
        niveau_cand_num = 0
        for langue_cand in langues_cand.split(";"):
            langue_cand = langue_cand.strip()
            if nom_langue.lower() in langue_cand.lower():
                if "(" in langue_cand:
                    _, niveau_cand_texte = langue_cand.split("(", 1)
                    niveau_cand_texte = niveau_cand_texte.strip().rstrip(")").lower()
                    niveau_cand_num = echelle_langues.get(niveau_cand_texte, 0)
                else:
                    niveau_cand_num = 2
                break
        
        if niveau_cand_num >= niveau_req_num:
            taux_langues_total += 1
        else:
            taux_langues_total += niveau_cand_num / niveau_req_num if niveau_req_num > 0 else 0
    
    taux_langues = taux_langues_total / len(langues_req_list) if len(langues_req_list) > 0 else 0
    
    return taux_comp, taux_exp, taux_diplome, taux_langues

# ============================================
# MAIN
# ============================================

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("0")
        sys.exit(1)
    
    chemin_cv = sys.argv[1]
    description_offre = sys.argv[2]
    ponderations = sys.argv[3]  # Format: "0.4,0.3,0.2,0.1"
    
    # Parser les pondérations
    poids = [float(x) for x in ponderations.split(",")]
    poids_comp, poids_exp, poids_dipl, poids_lang = poids
    
    # Extraire le texte du CV
    texte_cv = extraire_texte_pdf(chemin_cv)
    
    # Extraire les infos du CV
    competences_cand = extraire_competences(texte_cv)
    experience_cand = extraire_experience(texte_cv)
    diplome_cand = extraire_diplome(texte_cv)
    langues_cand = extraire_langues(texte_cv)
    
    # Extraire les exigences de l'offre
    exigences = extraire_exigences_offre(description_offre)
    
    # Calculer les taux
    taux_comp, taux_exp, taux_dipl, taux_lang = calculer_taux(
        exigences['competences'], exigences['experience'], exigences['diplome'], exigences['langues'],
        competences_cand, experience_cand, diplome_cand, langues_cand
    )
    
    # Prédiction
    nouvelle_candidature = pd.DataFrame({
        'taux_competences': [taux_comp],
        'taux_experience': [taux_exp],
        'taux_diplome': [taux_dipl],
        'taux_langues': [taux_lang],
        'poids_competences': [poids_comp],
        'poids_experience': [poids_exp],
        'poids_diplome': [poids_dipl],
        'poids_langues': [poids_lang]
    })
    
    score = modele.predict(nouvelle_candidature)
    print(round(float(score[0]), 2))