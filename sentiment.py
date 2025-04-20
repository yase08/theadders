"""
Enhanced Sentiment Analysis Module for Cryptocurrency

This module implements advanced sentiment analysis for cryptocurrency-related news
using an ensemble of models, entity recognition, and aspect-based analysis.
"""

import os
import logging
import time
import json
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple, Union, Any, Set
import re

import pandas as pd
import numpy as np
from newsapi import NewsApiClient
import nltk
from nltk.sentiment.vader import SentimentIntensityAnalyzer
import spacy
from transformers import pipeline, AutoModelForSequenceClassification, AutoTokenizer
import requests
import torch
from sklearn.ensemble import GradientBoostingRegressor

# Configure logging
logger = logging.getLogger(__name__)

# Download NLTK resources if not already downloaded
try:
    nltk.data.find('vader_lexicon')
except LookupError:
    nltk.download('vader_lexicon')

# Load spaCy model for NER
try:
    nlp = spacy.load("en_core_web_sm")
except OSError:
    logger.warning("Downloading spaCy model...")
    spacy.cli.download("en_core_web_sm")
    nlp = spacy.load("en_core_web_sm")


class EnhancedSentimentAnalyzer:
    """
    A class for analyzing sentiment of cryptocurrency-related content
    with entity recognition and aspect-based analysis.
    """
    
    def __init__(
        self,
        config: Dict[str, Any],
        models: List[str] = None,
        crypto_entities_file: str = "data/crypto_entities.json",
        aspect_terms_file: str = "data/aspect_terms.json",
        use_source_weighting: bool = True,
        use_time_weighting: bool = True,
    ):
        """
        Initialize the enhanced sentiment analyzer.
        
        Args:
            config (Dict[str, Any]): Configuration dictionary.
            models (List[str], optional): List of transformer models to use. Defaults to None.
            crypto_entities_file (str): File containing crypto entity names and aliases.
            aspect_terms_file (str): File containing aspect terms for different categories.
            use_source_weighting (bool): Whether to weight sources by credibility.
            use_time_weighting (bool): Whether to apply time decay to older articles.
        """
        self.config = config
        self.use_source_weighting = use_source_weighting
        self.use_time_weighting = use_time_weighting
        
        # Default models if none provided
        if models is None:
            models = [
                "distilbert-base-uncased-finetuned-sst-2-english",  # General sentiment
                "ProsusAI/finbert",  # Financial sentiment
                "cardiffnlp/twitter-roberta-base-sentiment-latest"  # Social media sentiment
            ]
        self.model_names = models
        
        # Initialize NewsAPI client
        newsapi_key = os.getenv("NEWSAPI_KEY")
        if newsapi_key is None:
            logger.warning("NewsAPI key not found in environment variables. News retrieval will not work.")
            self.newsapi = None
        else:
            self.newsapi = NewsApiClient(api_key=newsapi_key)
        
        # Initialize VADER
        self.vader = SentimentIntensityAnalyzer()
        
        # Initialize transformer models
        self.transformers = {}
        for model_name in models:
            try:
                self.transformers[model_name] = pipeline("sentiment-analysis", model=model_name)
                logger.info(f"Initialized transformer model: {model_name}")
            except Exception as e:
                logger.error(f"Error initializing transformer model {model_name}: {str(e)}")
        
        # Load crypto entities
        self.crypto_entities = self._load_crypto_entities(crypto_entities_file)
        
        # Load aspect terms
        self.aspect_terms = self._load_aspect_terms(aspect_terms_file)
        
        # Initialize source credibility data
        self.source_credibility = self._load_source_credibility()
        
        # Initialize ensemble model for combining sentiment scores
        self.ensemble_model = None
        self._initialize_ensemble_model()
    
    def _load_crypto_entities(self, file_path: str) -> Dict[str, Dict[str, Any]]:
        """
        Load cryptocurrency entities and their aliases from file.
        If file doesn't exist, create a default one.
        
        Args:
            file_path (str): Path to the crypto entities file.
            
        Returns:
            Dict[str, Dict[str, Any]]: Dictionary of crypto entities.
        """
        default_entities = {
            "bitcoin": {
                "aliases": ["btc", "bitcoin", "xbt", "satoshi"],
                "importance": 1.0
            },
            "ethereum": {
                "aliases": ["eth", "ethereum", "ether"],
                "importance": 0.9
            },
            "ripple": {
                "aliases": ["xrp", "ripple"],
                "importance": 0.7
            },
            "litecoin": {
                "aliases": ["ltc", "litecoin"],
                "importance": 0.6
            },
            "cardano": {
                "aliases": ["ada", "cardano"],
                "importance": 0.6
            },
            "crypto": {
                "aliases": ["cryptocurrency", "crypto", "cryptocurrencies", "digital currency"],
                "importance": 0.8
            }
        }
        
        try:
            if os.path.exists(file_path):
                with open(file_path, "r") as f:
                    return json.load(f)
            else:
                # Create default file
                os.makedirs(os.path.dirname(file_path), exist_ok=True)
                with open(file_path, "w") as f:
                    json.dump(default_entities, f, indent=4)
                return default_entities
        except Exception as e:
            logger.error(f"Error loading crypto entities: {str(e)}")
            return default_entities
    
    def _load_aspect_terms(self, file_path: str) -> Dict[str, List[str]]:
        """
        Load aspect terms for different categories from file.
        If file doesn't exist, create a default one.
        
        Args:
            file_path (str): Path to the aspect terms file.
            
        Returns:
            Dict[str, List[str]]: Dictionary of aspect terms by category.
        """
        default_aspects = {
            "price": [
                "price", "value", "cost", "worth", "expensive", "cheap",
                "bull", "bear", "bullish", "bearish", "moon", "dump", "pump",
                "surge", "plunge", "rise", "fall", "increase", "decrease",
                "all-time high", "ath", "all-time low", "crash"
            ],
            "technology": [
                "blockchain", "protocol", "algorithm", "mining", "miner",
                "hash", "node", "wallet", "transaction", "scalability",
                "layer", "smart contract", "token", "network", "fork",
                "consensus", "decentralized", "distributed", "security"
            ],
            "adoption": [
                "adoption", "mainstream", "institutional", "retail",
                "investor", "investment", "fund", "exchange", "trading",
                "payment", "merchant", "accept", "partnership", "integration",
                "user", "utility", "use case", "application", "dapp"
            ],
            "regulation": [
                "regulation", "regulatory", "law", "legal", "illegal",
                "compliance", "sec", "cftc", "congress", "legislature",
                "govern", "policy", "ban", "approve", "restrict", "tax",
                "kyc", "aml", "compliance", "fraud", "crime", "hack"
            ]
        }
        
        try:
            if os.path.exists(file_path):
                with open(file_path, "r") as f:
                    return json.load(f)
            else:
                # Create default file
                os.makedirs(os.path.dirname(file_path), exist_ok=True)
                with open(file_path, "w") as f:
                    json.dump(default_aspects, f, indent=4)
                return default_aspects
        except Exception as e:
            logger.error(f"Error loading aspect terms: {str(e)}")
            return default_aspects
    
    def _load_source_credibility(self) -> Dict[str, float]:
        """
        Load or create source credibility ratings.
        
        Returns:
            Dict[str, float]: Dictionary mapping source domains to credibility scores.
        """
        # Default credibility for major sources
        default_credibility = {
            "bloomberg.com": 0.9,
            "wsj.com": 0.9,
            "reuters.com": 0.9,
            "ft.com": 0.9,
            "cnbc.com": 0.8,
            "forbes.com": 0.8,
            "cointelegraph.com": 0.7,
            "coindesk.com": 0.7,
            "theblockcrypto.com": 0.7,
            "medium.com": 0.5,
            "reddit.com": 0.5,
            "twitter.com": 0.4,
            "default": 0.6  # Default for unknown sources
        }
        
        return default_credibility
    
    def _initialize_ensemble_model(self):
        """Initialize a simple ensemble model for combining sentiment scores."""
        # Default weights for different models
        self.model_weights = {
            "vader": 0.3,
            "distilbert-base-uncased-finetuned-sst-2-english": 0.2,
            "ProsusAI/finbert": 0.3,
            "cardiffnlp/twitter-roberta-base-sentiment-latest": 0.2
        }
        
        # Can be replaced with a trained model if we have training data
        self.ensemble_model = None  # Placeholder for trained model
    
    def _extract_domain(self, url: str) -> str:
        """
        Extract domain from URL.
        
        Args:
            url (str): The full URL.
            
        Returns:
            str: Domain name.
        """
        try:
            # Simple regex to extract domain
            match = re.search(r"https?://(?:www\.)?([^/]+)", url)
            if match:
                return match.group(1)
            return "unknown"
        except Exception:
            return "unknown"
    
    def _get_source_weight(self, url: str) -> float:
        """
        Get credibility weight for a source.
        
        Args:
            url (str): The source URL.
            
        Returns:
            float: Credibility weight between 0 and 1.
        """
        if not self.use_source_weighting:
            return 1.0
            
        domain = self._extract_domain(url)
        return self.source_credibility.get(domain, self.source_credibility["default"])
    
    def _get_time_weight(self, published_at: str) -> float:
        """
        Calculate time decay weight based on article age.
        
        Args:
            published_at (str): ISO format timestamp of publication.
            
        Returns:
            float: Time weight between 0 and 1.
        """
        if not self.use_time_weighting:
            return 1.0
            
        try:
            # Parse the timestamp
            pub_time = datetime.fromisoformat(published_at.replace("Z", "+00:00"))
            now = datetime.now().astimezone()
            
            # Calculate age in days
            age_days = (now - pub_time).total_seconds() / 86400.0
            
            # Apply exponential decay with half-life of 2 days
            weight = np.exp(-0.347 * age_days)  # ln(2)/2 ≈ 0.347
            return max(0.1, weight)  # Minimum weight of 0.1
        except Exception:
            return 1.0  # Default to full weight on error
    
    def _identify_entities_and_aspects(self, text: str) -> Dict[str, Dict[str, Set[str]]]:
        """
        Identify crypto entities and aspects in text.
        
        Args:
            text (str): The text to analyze.
            
        Returns:
            Dict[str, Dict[str, Set[str]]]: Dictionary mapping entities to aspects and their mentions.
        """
        text = text.lower()
        results = {}
        
        # Look for each entity and its aliases
        for entity, entity_data in self.crypto_entities.items():
            aliases = entity_data["aliases"]
            # Check for entity mentions
            for alias in aliases:
                pattern = r'\b' + re.escape(alias) + r'\b'
                if re.search(pattern, text):
                    if entity not in results:
                        results[entity] = {"mentions": set(), "aspects": {}}
                    results[entity]["mentions"].add(alias)
        
        # If no specific entities found, add general "crypto"
        if not results and "crypto" in self.crypto_entities:
            for alias in self.crypto_entities["crypto"]["aliases"]:
                pattern = r'\b' + re.escape(alias) + r'\b'
                if re.search(pattern, text):
                    results["crypto"] = {"mentions": set([alias]), "aspects": {}}
                    break
        
        # Look for aspects
        for entity in results:
            for aspect, terms in self.aspect_terms.items():
                aspect_mentions = set()
                for term in terms:
                    pattern = r'\b' + re.escape(term) + r'\b'
                    if re.search(pattern, text):
                        aspect_mentions.add(term)
                
                if aspect_mentions:
                    results[entity]["aspects"][aspect] = aspect_mentions
        
        return results
    
    def _analyze_with_vader(self, text: str) -> Dict[str, float]:
        """Analyze text sentiment using VADER."""
        try:
            scores = self.vader.polarity_scores(text)
            return {
                "compound": scores["compound"],
                "positive": scores["pos"],
                "negative": scores["neg"],
                "neutral": scores["neu"]
            }
        except Exception as e:
            logger.error(f"Error in VADER analysis: {str(e)}")
            return {"compound": 0.0, "positive": 0.0, "negative": 0.0, "neutral": 1.0}
    
    def _analyze_with_transformer(self, text: str, model_name: str) -> Dict[str, float]:
        """Analyze text sentiment using a specific transformer model."""
        if model_name not in self.transformers:
            return {"compound": 0.0, "positive": 0.0, "negative": 0.0, "neutral": 1.0}
        
        try:
            # Truncate text if too long (most models have a token limit)
            if len(text) > 512:
                text = text[:512]
            
            result = self.transformers[model_name](text)[0]
            
            # Handle different model output formats
            if model_name == "ProsusAI/finbert":
                # FinBERT has specific financial sentiment categories
                if result["label"] == "positive":
                    score = result["score"]
                    return {"compound": score, "positive": score, "negative": 0.0, "neutral": 1.0 - score}
                elif result["label"] == "negative":
                    score = result["score"]
                    return {"compound": -score, "positive": 0.0, "negative": score, "neutral": 1.0 - score}
                else:  # neutral
                    score = result["score"]
                    return {"compound": 0.0, "positive": 0.0, "negative": 0.0, "neutral": score}
            else:
                # Generic sentiment models typically have positive/negative labels
                if result["label"] in ["POSITIVE", "positive"]:
                    score = result["score"]
                    return {"compound": score, "positive": score, "negative": 0.0, "neutral": 1.0 - score}
                elif result["label"] in ["NEGATIVE", "negative"]:
                    score = result["score"]
                    return {"compound": -score, "positive": 0.0, "negative": score, "neutral": 1.0 - score}
                else:  # neutral
                    score = result["score"]
                    return {"compound": 0.0, "positive": 0.0, "negative": 0.0, "neutral": score}
        except Exception as e:
            logger.error(f"Error in transformer analysis with {model_name}: {str(e)}")
            return {"compound": 0.0, "positive": 0.0, "negative": 0.0, "neutral": 1.0}
    
    def analyze_text_ensemble(self, text: str) -> Dict[str, float]:
        """
        Analyze text sentiment using ensemble of models.
        
        Args:
            text (str): Text to analyze.
            
        Returns:
            Dict[str, float]: Combined sentiment scores.
        """
        # Get VADER sentiment
        vader_sentiment = self._analyze_with_vader(text)
        
        # Get transformer sentiments
        transformer_sentiments = {}
        for model_name in self.transformers:
            transformer_sentiments[model_name] = self._analyze_with_transformer(text, model_name)
        
        # Combine using weighted average (can be replaced with trained ensemble)
        compound = self.model_weights["vader"] * vader_sentiment["compound"]
        positive = self.model_weights["vader"] * vader_sentiment["positive"]
        negative = self.model_weights["vader"] * vader_sentiment["negative"]
        neutral = self.model_weights["vader"] * vader_sentiment["neutral"]
        
        for model_name, sentiment in transformer_sentiments.items():
            if model_name in self.model_weights:
                weight = self.model_weights[model_name]
                compound += weight * sentiment["compound"]
                positive += weight * sentiment["positive"]
                negative += weight * sentiment["negative"]
                neutral += weight * sentiment["neutral"]
        
        # Normalize positive, negative, neutral to sum to 1
        total = positive + negative + neutral
        if total > 0:
            positive /= total
            negative /= total
            neutral /= total
        
        return {
            "compound": compound,
            "positive": positive,
            "negative": negative,
            "neutral": neutral,
            "models_used": ["vader"] + list(transformer_sentiments.keys())
        }
    
    def analyze_text_with_entities(self, text: str) -> Dict[str, Any]:
        """
        Analyze text with entity and aspect detection.
        
        Args:
            text (str): Text to analyze.
            
        Returns:
            Dict[str, Any]: Sentiment analysis results by entity and aspect.
        """
        # Get overall sentiment
        overall_sentiment = self.analyze_text_ensemble(text)
        
        # Extract entities and aspects
        entities_aspects = self._identify_entities_and_aspects(text)
        
        # If no entities found, return overall sentiment only
        if not entities_aspects:
            return {
                "overall_sentiment": overall_sentiment,
                "entities": {}
            }
        
        # Analyze sentiment for each entity and aspect
        entity_sentiments = {}
        
        for entity, data in entities_aspects.items():
            # Process each aspect for this entity
            aspect_sentiments = {}
            
            if data["aspects"]:
                for aspect, mentions in data["aspects"].items():
                    # Create aspect-focused text
                    aspect_sentences = []
                    
                    # Split text into sentences (simple approach)
                    sentences = re.split(r'[.!?]+', text)
                    
                    # Find sentences containing both entity and aspect
                    for sentence in sentences:
                        sentence = sentence.lower().strip()
                        if not sentence:
                            continue
                            
                        # Check if sentence contains entity
                        entity_in_sentence = any(
                            re.search(r'\b' + re.escape(alias) + r'\b', sentence)
                            for alias in data["mentions"]
                        )
                        
                        # Check if sentence contains aspect
                        aspect_in_sentence = any(
                            re.search(r'\b' + re.escape(term) + r'\b', sentence)
                            for term in mentions
                        )
                        
                        if entity_in_sentence and aspect_in_sentence:
                            aspect_sentences.append(sentence)
                    
                    # If found relevant sentences, analyze them
                    if aspect_sentences:
                        aspect_text = " ".join(aspect_sentences)
                        aspect_sentiment = self.analyze_text_ensemble(aspect_text)
                        aspect_sentiments[aspect] = aspect_sentiment
                    else:
                        # If no sentences found with both entity and aspect, use overall sentiment
                        aspect_sentiments[aspect] = overall_sentiment
            
            # If no aspects found, analyze sentences with just the entity
            if not data["aspects"] or not aspect_sentiments:
                entity_sentences = []
                sentences = re.split(r'[.!?]+', text)
                
                for sentence in sentences:
                    sentence = sentence.lower().strip()
                    if not sentence:
                        continue
                        
                    # Check if sentence contains entity
                    entity_in_sentence = any(
                        re.search(r'\b' + re.escape(alias) + r'\b', sentence)
                        for alias in data["mentions"]
                    )
                    
                    if entity_in_sentence:
                        entity_sentences.append(sentence)
                
                if entity_sentences:
                    entity_text = " ".join(entity_sentences)
                    entity_sentiment = self.analyze_text_ensemble(entity_text)
                else:
                    entity_sentiment = overall_sentiment
                
                entity_sentiments[entity] = {
                    "sentiment": entity_sentiment,
                    "aspects": aspect_sentiments
                }
            else:
                # Calculate entity-level sentiment as weighted average of aspect sentiments
                entity_compound = 0.0
                total_weight = 0.0
                
                for aspect, sentiment in aspect_sentiments.items():
                    # Aspects could have different weights (price might be more important)
                    aspect_weight = 1.0  # Default weight
                    entity_compound += sentiment["compound"] * aspect_weight
                    total_weight += aspect_weight
                
                if total_weight > 0:
                    entity_compound /= total_weight
                
                entity_sentiment = {
                    "compound": entity_compound,
                    "aspect_count": len(aspect_sentiments)
                }
                
                entity_sentiments[entity] = {
                    "sentiment": entity_sentiment,
                    "aspects": aspect_sentiments
                }
        
        return {
            "overall_sentiment": overall_sentiment,
            "entities": entity_sentiments
        }
    
    def get_news_articles(
        self,
        keywords: List[str] = None,
        days: int = 3,
        language: str = "en",
        max_articles: int = 100
    ) -> List[Dict[str, Any]]:
        """
        Retrieve news articles related to cryptocurrencies.
        
        Args:
            keywords (List[str], optional): List of keywords to search for.
            days (int, optional): Number of days to look back.
            language (str, optional): Language of articles.
            max_articles (int, optional): Maximum number of articles to retrieve.
            
        Returns:
            List[Dict[str, Any]]: List of news articles.
        """
        if self.newsapi is None:
            logger.error("NewsAPI client not initialized. Cannot retrieve news.")
            return []
        
        if keywords is None:
            keywords = self.config.get("sentiment", {}).get("keywords", ["bitcoin", "crypto", "cryptocurrency"])
        
        articles = []
        
        try:
            # Calculate date range
            end_date = datetime.now()
            start_date = end_date - timedelta(days=days)
            
            # Format dates for NewsAPI
            from_date = start_date.strftime("%Y-%m-%d")
            to_date = end_date.strftime("%Y-%m-%d")
            
            # Create the query string
            query = " OR ".join(keywords)
            
            # Get articles from NewsAPI
            response = self.newsapi.get_everything(
                q=query,
                from_param=from_date,
                to=to_date,
                language=language,
                sort_by="publishedAt",
                page_size=min(max_articles, 100),  # API limitation
                page=1
            )
            
            # Extract articles
            if response and "articles" in response:
                articles = response["articles"]
                
                # If we need more articles and there are more pages
                page = 2
                while len(articles) < max_articles and len(response["articles"]) == 100:
                    response = self.newsapi.get_everything(
                        q=query,
                        from_param=from_date,
                        to=to_date,
                        language=language,
                        sort_by="publishedAt",
                        page_size=min(max_articles - len(articles), 100),
                        page=page
                    )
                    
                    if response and "articles" in response:
                        articles.extend(response["articles"])
                        page += 1
                    else:
                        break
            
            logger.info(f"Retrieved {len(articles)} news articles")
            return articles
        except Exception as e:
            logger.error(f"Error retrieving news articles: {str(e)}")
            return []
    
    def analyze_articles(self, articles: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Analyze sentiment of a list of news articles with entity recognition.
        
        Args:
            articles (List[Dict[str, Any]]): List of news articles.
            
        Returns:
            Dict[str, Any]: Dictionary with sentiment analysis results.
        """
        if not articles:
            logger.warning("No articles to analyze")
            return {
                "overall_sentiment": 0.0,
                "entity_sentiments": {},
                "aspect_sentiments": {},
                "article_count": 0
            }
        
        try:
            # Process each article
            article_results = []
            entity_sentiments = {}
            aspect_sentiments = {}
            
            for article in articles:
                # Combine title and description for analysis
                title = article.get("title", "")
                description = article.get("description", "")
                content = article.get("content", "")
                
                # Use the most complete text available
                if content and len(content) > len(description):
                    text = f"{title}. {content}"
                else:
                    text = f"{title}. {description}"
                
                if not text.strip():
                    continue
                
                # Get source and time weights
                source_weight = self._get_source_weight(article.get("url", ""))
                time_weight = self._get_time_weight(article.get("publishedAt", datetime.now().isoformat()))
                combined_weight = source_weight * time_weight
                
                # Analyze with entity and aspect detection
                analysis = self.analyze_text_with_entities(text)
                
                # Store analysis with metadata
                article_result = {
                    "title": title,
                    "url": article.get("url", ""),
                    "source": article.get("source", {}).get("name", ""),
                    "publishedAt": article.get("publishedAt", ""),
                    "overall_sentiment": analysis["overall_sentiment"],
                    "entity_sentiments": analysis.get("entities", {}),
                    "source_weight": source_weight,
                    "time_weight": time_weight,
                    "combined_weight": combined_weight
                }
                
                article_results.append(article_result)
                
                # Aggregate entity and aspect sentiments
                for entity, entity_data in analysis.get("entities", {}).items():
                    if entity not in entity_sentiments:
                        entity_sentiments[entity] = {
                            "compound_sum": 0.0,
                            "weight_sum": 0.0,
                            "article_count": 0,
                            "aspects": {}
                        }
                    
                    # Add weighted entity sentiment
                    entity_compound = entity_data.get("sentiment", {}).get("compound", 0.0)
                    entity_sentiments[entity]["compound_sum"] += entity_compound * combined_weight
                    entity_sentiments[entity]["weight_sum"] += combined_weight
                    entity_sentiments[entity]["article_count"] += 1
                    
                    # Add aspect sentiments
                    for aspect, aspect_data in entity_data.get("aspects", {}).items():
                        if aspect not in entity_sentiments[entity]["aspects"]:
                            entity_sentiments[entity]["aspects"][aspect] = {
                                "compound_sum": 0.0,
                                "weight_sum": 0.0,
                                "article_count": 0
                            }
                        
                        aspect_compound = aspect_data.get("compound", 0.0)
                        entity_sentiments[entity]["aspects"][aspect]["compound_sum"] += aspect_compound * combined_weight
                        entity_sentiments[entity]["aspects"][aspect]["weight_sum"] += combined_weight
                        entity_sentiments[entity]["aspects"][aspect]["article_count"] += 1
                        
                        # Also track aspects across all entities
                        if aspect not in aspect_sentiments:
                            aspect_sentiments[aspect] = {
                                "compound_sum": 0.0,
                                "weight_sum": 0.0,
                                "article_count": 0
                            }
                        
                        aspect_sentiments[aspect]["compound_sum"] += aspect_compound * combined_weight
                        aspect_sentiments[aspect]["weight_sum"] += combined_weight
                        aspect_sentiments[aspect]["article_count"] += 1
            
            # Calculate final weighted sentiment for each entity
            final_entity_sentiments = {}
            for entity, data in entity_sentiments.items():
                if data["weight_sum"] > 0:
                    final_entity_sentiments[entity] = {
                        "compound": data["compound_sum"] / data["weight_sum"],
                        "article_count": data["article_count"],
                        "aspects": {}
                    }
                    
                    # Calculate sentiment for each aspect
                    for aspect, aspect_data in data["aspects"].items():
                        if aspect_data["weight_sum"] > 0:
                            final_entity_sentiments[entity]["aspects"][aspect] = {
                                "compound": aspect_data["compound_sum"] / aspect_data["weight_sum"],
                                "article_count": aspect_data["article_count"]
                            }
            
            # Calculate final weighted sentiment for each aspect
            final_aspect_sentiments = {}
            for aspect, data in aspect_sentiments.items():
                if data["weight_sum"] > 0:
                    final_aspect_sentiments[aspect] = {
                        "compound": data["compound_sum"] / data["weight_sum"],
                        "article_count": data["article_count"]
                    }
            
            # Calculate overall sentiment as weighted average of all articles
            overall_compound_sum = 0.0
            overall_weight_sum = 0.0
            
            for result in article_results:
                compound = result["overall_sentiment"].get("compound", 0.0)
                weight = result["combined_weight"]
                overall_compound_sum += compound * weight
                overall_weight_sum += weight
            
            if overall_weight_sum > 0:
                overall_sentiment = overall_compound_sum / overall_weight_sum
            else:
                overall_sentiment = 0.0
            
            logger.info(f"Analyzed sentiment of {len(article_results)} articles. Overall sentiment: {overall_sentiment:.4f}")
            
            return {
                "overall_sentiment": overall_sentiment,
                "entity_sentiments": final_entity_sentiments,
                "aspect_sentiments": final_aspect_sentiments,
                "article_results": article_results,
                "article_count": len(article_results)
            }
        except Exception as e:
            logger.error(f"Error analyzing articles: {str(e)}")
            return {
                "overall_sentiment": 0.0,
                "entity_sentiments": {},
                "aspect_sentiments": {},
                "article_count": 0
            }
    
    def get_market_sentiment(
        self,
        keywords: Optional[List[str]] = None,
        days: int = 3,
        refresh: bool = False,
        cache_file: str = "data/sentiment_cache.json"
    ) -> Dict[str, Any]:
        """
        Get market sentiment based on news articles with entity and aspect analysis.
        
        Args:
            keywords (List[str], optional): List of keywords to search for. Defaults to None.
            days (int, optional): Number of days to look back. Defaults to 3.
            refresh (bool, optional): Whether to force refresh the data. Defaults to False.
            cache_file (str, optional): Path to cache file. Defaults to "data/sentiment_cache.json".
            
        Returns:
            Dict[str, Any]: Dictionary with market sentiment data.
        """
        # Check if cached data exists and is recent enough
        if not refresh and os.path.exists(cache_file):
            try:
                with open(cache_file, "r") as f:
                    cache_data = json.load(f)
                
                # Check if cache is still valid
                cache_time = datetime.fromisoformat(cache_data.get("timestamp", "2000-01-01"))
                cache_expiry = timedelta(hours=self.config.get("sentiment", {}).get("cache_expiry_hours", 3))
                
                if datetime.now() - cache_time < cache_expiry:
                    logger.info(f"Using cached sentiment data from {cache_time}")
                    return cache_data
            except Exception as e:
                logger.error(f"Error reading sentiment cache: {str(e)}")
        
        # Get keywords from config if not provided
        if keywords is None:
            keywords = self.config.get("sentiment", {}).get("keywords", ["bitcoin", "crypto", "cryptocurrency"])
        
        # Get max articles from config
        max_articles = self.config.get("sentiment", {}).get("max_articles", 100)
        
        # Get news articles
        articles = self.get_news_articles(keywords=keywords, days=days, max_articles=max_articles)
        
        # Analyze sentiment
        sentiment_data = self.analyze_articles(articles)
        
        # Add timestamp and metadata
        sentiment_data["timestamp"] = datetime.now().isoformat()
        sentiment_data["keywords"] = keywords
        sentiment_data["days"] = days
        
        # Cache the data
        try:
            os.makedirs(os.path.dirname(cache_file), exist_ok=True)
            with open(cache_file, "w") as f:
                json.dump(sentiment_data, f)
            logger.info(f"Cached sentiment data to {cache_file}")
        except Exception as e:
            logger.error(f"Error caching sentiment data: {str(e)}")
        
        return sentiment_data
    
    def get_entity_sentiment(
        self, 
        entity: str, 
        sentiment_data: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Get sentiment data for a specific entity.
        
        Args:
            entity (str): The entity to get sentiment for.
            sentiment_data (Dict[str, Any]): Full sentiment data from get_market_sentiment.
            
        Returns:
            Dict[str, Any]: Entity-specific sentiment data.
        """
        entity_sentiments = sentiment_data.get("entity_sentiments", {})
        
        if entity in entity_sentiments:
            return {
                "entity": entity,
                "compound": entity_sentiments[entity].get("compound", 0.0),
                "article_count": entity_sentiments[entity].get("article_count", 0),
                "aspects": entity_sentiments[entity].get("aspects", {})
            }
        else:
            # Check if aliases match
            for entity_key, entity_data in self.crypto_entities.items():
                aliases = entity_data.get("aliases", [])
                if entity.lower() in [a.lower() for a in aliases]:
                    if entity_key in entity_sentiments:
                        return {
                            "entity": entity_key,
                            "compound": entity_sentiments[entity_key].get("compound", 0.0),
                            "article_count": entity_sentiments[entity_key].get("article_count", 0),
                            "aspects": entity_sentiments[entity_key].get("aspects", {})
                        }
            
            # Entity not found
            return {
                "entity": entity,
                "compound": 0.0,
                "article_count": 0,
                "aspects": {},
                "error": "Entity not found in sentiment data"
            }
    
    def get_sentiment_category(self, score: float) -> str:
        """
        Get a sentiment category label based on the score.
        
        Args:
            score (float): Sentiment score.
            
        Returns:
            str: Sentiment category.
        """
        if score >= 0.6:
            return "very bullish"
        elif score >= 0.2:
            return "bullish"
        elif score > -0.2:
            return "neutral"
        elif score > -0.6:
            return "bearish"
        else:
            return "very bearish"
    
    def analyze(
        self, 
        keywords: Optional[List[str]] = None, 
        days: int = 3, 
        refresh: bool = False,
        focus_entity: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Main entry point for sentiment analysis. Gets sentiment data and processes it.
        
        Args:
            keywords (List[str], optional): List of keywords to search for. Defaults to None.
            days (int, optional): Number of days to look back for articles. Defaults to 3.
            refresh (bool, optional): Whether to force refresh the data. Defaults to False.
            focus_entity (str, optional): Entity to focus on. If provided, returns detailed entity data.
            
        Returns:
            Dict[str, Any]: A dictionary containing sentiment analysis results including:
                - overall_sentiment: float value indicating overall sentiment (-1 to 1)
                - sentiment_category: overall sentiment category label
                - entity_sentiments: sentiment breakdown by entity
                - aspect_sentiments: sentiment breakdown by aspect
                - timestamp: when the analysis was performed
        """
        # Get market sentiment data
        sentiment_data = self.get_market_sentiment(
            keywords=keywords,
            days=days,
            refresh=refresh
        )
        
        # Get overall sentiment score
        overall_sentiment = sentiment_data.get("overall_sentiment", 0.0)
        sentiment_category = self.get_sentiment_category(overall_sentiment)
        
        # Extract entity sentiment data
        entity_summaries = {}
        entity_sentiments = sentiment_data.get("entity_sentiments", {})
        
        for entity, data in entity_sentiments.items():
            compound = data.get("compound", 0.0)
            category = self.get_sentiment_category(compound)
            
            # Summarize aspect sentiment
            aspect_summary = {}
            for aspect, aspect_data in data.get("aspects", {}).items():
                aspect_compound = aspect_data.get("compound", 0.0)
                aspect_category = self.get_sentiment_category(aspect_compound)
                aspect_summary[aspect] = {
                    "sentiment": aspect_compound,
                    "category": aspect_category,
                    "article_count": aspect_data.get("article_count", 0)
                }
            
            # Get entity importance
            importance = self.crypto_entities.get(entity, {}).get("importance", 0.5)
            
            entity_summaries[entity] = {
                "sentiment": compound,
                "category": category,
                "importance": importance,
                "article_count": data.get("article_count", 0),
                "aspects": aspect_summary
            }
        
        # If focusing on a specific entity, add detailed data
        focus_entity_data = None
        if focus_entity:
            focus_entity_data = self.get_entity_sentiment(focus_entity, sentiment_data)
            
            # Add entity-specific articles
            if focus_entity_data and "error" not in focus_entity_data:
                entity_articles = []
                for article in sentiment_data.get("article_results", []):
                    entity_sentiments = article.get("entity_sentiments", {})
                    if focus_entity in entity_sentiments:
                        entity_articles.append({
                            "title": article.get("title", ""),
                            "url": article.get("url", ""),
                            "source": article.get("source", ""),
                            "publishedAt": article.get("publishedAt", ""),
                            "sentiment": entity_sentiments[focus_entity].get("sentiment", {}).get("compound", 0.0)
                        })
                
                focus_entity_data["articles"] = entity_articles
        
        # Build final result
        result = {
            "overall_sentiment": overall_sentiment,
            "sentiment_category": sentiment_category,
            "entity_sentiments": entity_summaries,
            "aspect_sentiments": sentiment_data.get("aspect_sentiments", {}),
            "article_count": sentiment_data.get("article_count", 0),
            "timestamp": sentiment_data.get("timestamp", datetime.now().isoformat()),
            "keywords": sentiment_data.get("keywords", []),
            "days_analyzed": sentiment_data.get("days", days)
        }
        
        if focus_entity_data:
            result["focus_entity"] = focus_entity_data
        
        logger.info(f"Sentiment analysis complete. Overall sentiment: {overall_sentiment:.4f} ({sentiment_category})")
        
        return result
    
    def generate_sentiment_report(
        self, 
        analysis_result: Dict[str, Any],
        detailed: bool = False
    ) -> str:
        """
        Generate a readable sentiment report from analysis results.
        
        Args:
            analysis_result (Dict[str, Any]): Results from analyze() method.
            detailed (bool): Whether to include detailed breakdown.
            
        Returns:
            str: Formatted sentiment report.
        """
        report = []
        
        # Add header
        timestamp = datetime.fromisoformat(analysis_result.get("timestamp", datetime.now().isoformat()).replace("Z", "+00:00"))
        report.append(f"CRYPTOCURRENCY SENTIMENT REPORT - {timestamp.strftime('%Y-%m-%d %H:%M')}")
        report.append("=" * 80)
        report.append("")
        
        # Add summary
        overall = analysis_result.get("overall_sentiment", 0.0)
        category = analysis_result.get("sentiment_category", "neutral")
        article_count = analysis_result.get("article_count", 0)
        days = analysis_result.get("days_analyzed", 3)
        
        report.append(f"Overall Market Sentiment: {overall:.2f} ({category.upper()})")
        report.append(f"Based on {article_count} articles from the past {days} days")
        report.append("")
        
        # Add entity breakdown
        report.append("SENTIMENT BY CRYPTOCURRENCY")
        report.append("-" * 40)
        
        # Sort entities by importance and sentiment
        entities = list(analysis_result.get("entity_sentiments", {}).items())
        entities.sort(key=lambda x: (x[1].get("importance", 0.0), abs(x[1].get("sentiment", 0.0))), reverse=True)
        
        for entity, data in entities:
            sentiment = data.get("sentiment", 0.0)
            category = data.get("category", "neutral")
            article_count = data.get("article_count", 0)
            
            report.append(f"{entity.upper()}: {sentiment:.2f} ({category}) - {article_count} mentions")
            
            # Add aspect breakdown for this entity if detailed
            if detailed:
                aspects = data.get("aspects", {})
                if aspects:
                    for aspect, aspect_data in aspects.items():
                        aspect_sentiment = aspect_data.get("sentiment", 0.0)
                        aspect_category = aspect_data.get("category", "neutral")
                        aspect_count = aspect_data.get("article_count", 0)
                        report.append(f"  • {aspect}: {aspect_sentiment:.2f} ({aspect_category}) - {aspect_count} mentions")
        
        report.append("")
        
        # Add focus entity details if available
        focus_entity = analysis_result.get("focus_entity")
        if focus_entity and detailed:
            entity_name = focus_entity.get("entity", "").upper()
            compound = focus_entity.get("compound", 0.0)
            category = self.get_sentiment_category(compound)
            
            report.append(f"DETAILED ANALYSIS: {entity_name}")
            report.append("-" * 40)
            report.append(f"Sentiment Score: {compound:.2f} ({category.upper()})")
            
            # Add aspect breakdown
            aspects = focus_entity.get("aspects", {})
            if aspects:
                report.append("Aspect Analysis:")
                for aspect, aspect_data in aspects.items():
                    aspect_compound = aspect_data.get("compound", 0.0)
                    aspect_category = self.get_sentiment_category(aspect_compound)
                    report.append(f"  • {aspect}: {aspect_compound:.2f} ({aspect_category})")
            
            # Add related articles
            articles = focus_entity.get("articles", [])
            if articles:
                report.append("\nTop Articles:")
                # Sort by absolute sentiment strength
                articles.sort(key=lambda x: abs(x.get("sentiment", 0.0)), reverse=True)
                for i, article in enumerate(articles[:5], 1):
                    title = article.get("title", "")
                    source = article.get("source", "")
                    sentiment = article.get("sentiment", 0.0)
                    category = self.get_sentiment_category(sentiment)
                    report.append(f"  {i}. {title} ({source}) - {sentiment:.2f} ({category})")
            
            report.append("")
        
        # Add disclaimer
        report.append("\nDISCLAIMER: This sentiment analysis is for informational purposes only and should not")
        report.append("be considered as financial advice. Cryptocurrency markets are highly volatile and")
        report.append("sentiment analysis is just one of many factors to consider.")
        
        return "\n".join(report)
    
    def update_model_weights(self, new_weights: Dict[str, float]):
        """
        Update the weights used in the ensemble model.
        
        Args:
            new_weights (Dict[str, float]): New weights for each model.
        """
        # Validate weights
        total = sum(new_weights.values())
        if total == 0:
            logger.error("Invalid model weights: sum is zero")
            return
        
        # Normalize weights to sum to 1
        normalized_weights = {k: v / total for k, v in new_weights.items()}
        
        # Update weights
        self.model_weights.update(normalized_weights)
        logger.info(f"Updated model weights: {self.model_weights}")
    
    def train_on_market_data(
        self, 
        sentiment_data: List[Dict[str, Any]], 
        price_changes: List[float],
        learning_rate: float = 0.1
    ):
        """
        Train the ensemble model using historical market data.
        
        Args:
            sentiment_data (List[Dict[str, Any]]): List of sentiment analysis results.
            price_changes (List[float]): Corresponding price changes.
            learning_rate (float): Learning rate for training.
        """
        if len(sentiment_data) != len(price_changes):
            logger.error("Sentiment data and price changes must have the same length")
            return
        
        if not sentiment_data:
            logger.error("No training data provided")
            return
        
        try:
            # Extract features from sentiment data
            X = []
            for data in sentiment_data:
                # Extract model scores for each day
                features = []
                for model in self.model_weights.keys():
                    if model == "vader":
                        # Get VADER score from the data
                        score = data.get("vader_score", 0.0)
                    else:
                        # Get transformer model score from the data
                        score = data.get("transformer_scores", {}).get(model, 0.0)
                    features.append(score)
                X.append(features)
            
            # Train a gradient boosting model
            model = GradientBoostingRegressor(
                n_estimators=100,
                learning_rate=learning_rate,
                max_depth=3,
                random_state=42
            )
            model.fit(X, price_changes)
            
            # Extract feature importances as new weights
            importance = model.feature_importances_
            
            # Update model weights based on importance
            new_weights = {}
            for i, model_name in enumerate(self.model_weights.keys()):
                new_weights[model_name] = importance[i]
            
            # Update the weights
            self.update_model_weights(new_weights)
            
            # Save the trained ensemble model
            self.ensemble_model = model
            
            logger.info("Successfully trained ensemble model on market data")
            return model
        except Exception as e:
            logger.error(f"Error training on market data: {str(e)}")
            return None


# Usage example
if __name__ == "__main__":
    # Setup basic configuration
    config = {
        "sentiment": {
            "keywords": ["bitcoin", "ethereum", "crypto", "cryptocurrency"],
            "max_articles": 100,
            "cache_expiry_hours": 3
        }
    }
    
    # Initialize the analyzer
    analyzer = EnhancedSentimentAnalyzer(config)
    
    # Get market sentiment
    sentiment = analyzer.analyze(days=2, refresh=True)
    
    # Generate a report
    report = analyzer.generate_sentiment_report(sentiment, detailed=True)
    print(report)
    
    # Get sentiment for a specific entity
    bitcoin_sentiment = analyzer.analyze(focus_entity="bitcoin")
    
    # Generate entity-specific report
    bitcoin_report = analyzer.generate_sentiment_report(bitcoin_sentiment, detailed=True)
    print(bitcoin_report)