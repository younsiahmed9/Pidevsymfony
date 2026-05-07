# Hybrid Recommendation System Documentation

## Overview

This Symfony application implements a sophisticated hybrid recommendation system that combines content-based filtering and collaborative filtering to provide personalized service recommendations to users.

## Algorithm Details

### 1. Content-Based Filtering
Content-based filtering recommends services based on their similarity to services the user has previously interacted with.

**Implementation:**
- Analyzes user's interaction history to identify preferred service categories
- Calculates category similarity scores between services
- Uses service types (abonnement, facture) as primary categories
- Weights interactions by type (view=1.0, favorite=2.0, purchase=3.0, review=2.5)

**Formula:**
```
ContentScore(service) = CategorySimilarity(service, userPreferredCategories)
```

### 2. Collaborative Filtering
Collaborative filtering finds users with similar interaction patterns and recommends services they liked.

**Implementation:**
- Uses cosine similarity to find similar users based on interaction vectors
- Interaction vectors incorporate rating, frequency, and interaction type weights
- Recommends services that similar users have positively rated
- Limits to top 50 most similar users for performance

**Formula:**
```
Similarity(user1, user2) = CosineSimilarity(interactionVector1, interactionVector2)
CollaborativeScore(service) = WeightedAverage(similarUsers, serviceInteractions)
```

### 3. Hybrid Scoring Algorithm
The hybrid approach combines both filtering methods with weighted scoring:

**Formula:**
```
FinalScore = (ContentScore × 0.4) + (CollaborativeScore × 0.6) + (PopularityBoost × 0.1)
```

**Weight Distribution:**
- Content-based filtering: 40%
- Collaborative filtering: 60%
- Popularity boost: 10%

### 4. Popularity Boost
Services get additional score based on overall popularity:
- Interaction count (normalized)
- Average rating (normalized)
- Combined popularity score (0.0 to 1.0)

## Performance Optimizations

### 1. Caching
- In-memory caching with 1-hour TTL
- Cache keys: `recommendations_{userId}_{limit}`
- Manual cache clearing endpoint for administrators

### 2. Database Optimization
- Indexed queries on user_id and service_id
- Efficient DQL queries with proper joins
- Batch processing for large datasets

### 3. Algorithm Efficiency
- Limits similar users to top 50 for computational efficiency
- Vector-based similarity calculations
- Pre-computed popularity metrics

## API Endpoints

### GET `/api/recommendations/`
Get personalized recommendations for the current user.

**Parameters:**
- `limit` (optional): Number of recommendations (default: 5, max: 10)

**Response:**
```json
{
  "success": true,
  "data": {
    "recommendations": [
      {
        "service": {
          "id": 1,
          "name": "Netflix Premium",
          "type": "abonnement",
          "tarif": "15.99",
          "statut": "actif"
        },
        "recommendation": {
          "score": 0.8456,
          "type": "hybrid",
          "content_score": 0.7200,
          "collaborative_score": 0.9100,
          "popularity_boost": 0.0500
        }
      }
    ],
    "metadata": {
      "user_id": 123,
      "limit": 5,
      "count": 5,
      "algorithm": "hybrid_content_collaborative"
    }
  }
}
```

### POST `/api/recommendations/interaction`
Record user interaction with a service.

**Request Body:**
```json
{
  "service_id": 1,
  "interaction_type": "purchase",
  "rating": 4.5
}
```

**Interaction Types:**
- `view`: User viewed the service
- `favorite`: User favorited the service
- `purchase`: User purchased/subscribed to the service
- `review`: User reviewed the service

### GET `/api/recommendations/category/{category}`
Get recommendations for a specific service category.

### POST `/api/recommendations/cache/clear` (Admin only)
Clear the recommendation cache.

## Database Schema

### Core Tables

#### `service_category`
- `id`: Primary key
- `name`: Category name
- `description`: Category description
- `popularity`: Popularity score

#### `user_service_interaction`
- `id`: Primary key
- `user_id`: Foreign key to user
- `service_id`: Foreign key to service
- `interaction_type`: Type of interaction
- `rating`: User rating (0.0-5.0)
- `created_at`: Interaction timestamp
- `frequency`: Number of interactions

## Usage Examples

### Basic Recommendation Request
```bash
curl -X GET "http://localhost:8000/api/recommendations/?limit=5" \
  -H "Authorization: Bearer {token}"
```

### Recording Interaction
```bash
curl -X POST "http://localhost:8000/api/recommendations/interaction" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "service_id": 1,
    "interaction_type": "purchase",
    "rating": 4.5
  }'
```

## Configuration

### Environment Variables
```env
# Recommendation system settings
RECOMMENDATION_CACHE_TTL=3600
RECOMMENDATION_CONTENT_WEIGHT=0.4
RECOMMENDATION_COLLABORATIVE_WEIGHT=0.6
RECOMMENDATION_POPULARITY_BOOST=0.1
RECOMMENDATION_SIMILARITY_THRESHOLD=0.3
RECOMMENDATION_MAX_SIMILAR_USERS=50
```

## Testing

### Load Sample Data
```bash
php bin/console doctrine:fixtures:load --group=recommendation
```

### Clear Cache
```bash
php bin/console cache:clear
```

## Monitoring and Logging

The system logs:
- Recommendation generation events
- Performance metrics
- Similarity calculation results
- Cache operations

Log levels:
- `INFO`: Normal operations
- `WARNING`: Low similarity scores
- `ERROR`: System failures

## Scalability Considerations

### For Large Datasets (100K+ users, 10K+ services):

1. **Database Optimization:**
   - Partition interactions table by date
   - Use read replicas for recommendation queries
   - Implement connection pooling

2. **Algorithm Optimization:**
   - Use approximate nearest neighbor algorithms
   - Implement incremental similarity updates
   - Pre-compute user clusters

3. **Caching Strategy:**
   - Redis for distributed caching
   - Background cache warming
   - Cache invalidation on user interactions

4. **Performance Monitoring:**
   - Track recommendation generation time
   - Monitor cache hit rates
   - Alert on performance degradation

## Future Enhancements

1. **Machine Learning Integration:**
   - Neural collaborative filtering
   - Deep learning for feature extraction
   - Real-time model updates

2. **Advanced Features:**
   - Cold-start problem solutions
   - Context-aware recommendations
   - A/B testing framework

3. **Personalization Improvements:**
   - User segmentation
   - Time-based preferences
   - Seasonal recommendations
