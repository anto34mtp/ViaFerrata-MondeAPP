import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Image,
} from 'react-native';
import {Via} from '../api/client';
import DifficultyBadge from './DifficultyBadge';
import StatusBadge from './StatusBadge';
import {formatDuration} from '../utils/helpers';

interface Props {
  via: Via;
  onPress: () => void;
}

const ViaCard: React.FC<Props> = ({via, onPress}) => {
  const coverPhoto = via.photos && via.photos.length > 0 ? via.photos[0].url : null;

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.8}>
      {coverPhoto ? (
        <Image source={{uri: coverPhoto}} style={styles.image} resizeMode="cover" />
      ) : (
        <View style={[styles.image, styles.imagePlaceholder]}>
          <Text style={styles.imagePlaceholderText}>🏔️</Text>
        </View>
      )}
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.name} numberOfLines={2}>
            {via.name}
          </Text>
          <DifficultyBadge level={via.difficulty} size="small" />
        </View>
        <Text style={styles.location} numberOfLines={1}>
          {[via.location, via.department_name, via.country]
            .filter(Boolean)
            .join(' · ')}
        </Text>
        <View style={styles.footer}>
          <View style={styles.meta}>
            {via.duration_min || via.duration_max ? (
              <Text style={styles.metaText}>
                ⏱ {formatDuration(via.duration_min, via.duration_max)}
              </Text>
            ) : null}
            {via.length_m ? (
              <Text style={styles.metaText}>📏 {via.length_m}m</Text>
            ) : null}
            {via.elevation_m ? (
              <Text style={styles.metaText}>↑ {via.elevation_m}m</Text>
            ) : null}
          </View>
          <View style={styles.rightMeta}>
            {via.avg_rating_general ? (
              <Text style={styles.rating}>
                ★ {via.avg_rating_general.toFixed(1)}
              </Text>
            ) : null}
            {via.opening_status ? (
              <StatusBadge status={via.opening_status} />
            ) : null}
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    marginVertical: 6,
    marginHorizontal: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 3,
    overflow: 'hidden',
  },
  image: {
    width: '100%',
    height: 140,
  },
  imagePlaceholder: {
    backgroundColor: '#E8F5E9',
    justifyContent: 'center',
    alignItems: 'center',
  },
  imagePlaceholderText: {
    fontSize: 40,
  },
  content: {
    padding: 12,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  name: {
    flex: 1,
    fontSize: 16,
    fontWeight: '700',
    color: '#1A1A1A',
    marginRight: 8,
  },
  location: {
    fontSize: 13,
    color: '#666',
    marginBottom: 8,
  },
  footer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  meta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  metaText: {
    fontSize: 12,
    color: '#555',
    marginRight: 8,
  },
  rightMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  rating: {
    fontSize: 13,
    color: '#F57F17',
    fontWeight: '700',
    marginRight: 6,
  },
});

export default ViaCard;
