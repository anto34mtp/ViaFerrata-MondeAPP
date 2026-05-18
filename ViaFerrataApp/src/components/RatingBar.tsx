import React from 'react';
import {View, Text, TouchableOpacity, StyleSheet} from 'react-native';

interface Props {
  label: string;
  value: number;
  onValueChange?: (val: number) => void;
  readonly?: boolean;
  maxStars?: number;
}

const RatingBar: React.FC<Props> = ({
  label,
  value,
  onValueChange,
  readonly = false,
  maxStars = 5,
}) => {
  return (
    <View style={styles.container}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.stars}>
        {Array.from({length: maxStars}, (_, i) => i + 1).map(star => (
          <TouchableOpacity
            key={star}
            onPress={() => !readonly && onValueChange && onValueChange(star)}
            disabled={readonly}
            style={styles.star}>
            <Text style={[styles.starText, star <= value ? styles.starFilled : styles.starEmpty]}>
              {star <= value ? '★' : '☆'}
            </Text>
          </TouchableOpacity>
        ))}
        {value > 0 && (
          <Text style={styles.valueText}>{value.toFixed(1)}</Text>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 4,
  },
  label: {
    flex: 1,
    fontSize: 14,
    color: '#333',
  },
  stars: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  star: {
    padding: 2,
  },
  starText: {
    fontSize: 20,
  },
  starFilled: {
    color: '#FFC107',
  },
  starEmpty: {
    color: '#BDBDBD',
  },
  valueText: {
    marginLeft: 6,
    fontSize: 13,
    color: '#666',
    fontWeight: '600',
  },
});

export default RatingBar;
