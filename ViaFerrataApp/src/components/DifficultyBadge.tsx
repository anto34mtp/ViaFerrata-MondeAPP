import React from 'react';
import {View, Text, StyleSheet} from 'react-native';
import {getDifficultyLabel, getDifficultyColor} from '../utils/helpers';

interface Props {
  level?: number;
  size?: 'small' | 'medium' | 'large';
}

const DifficultyBadge: React.FC<Props> = ({level, size = 'medium'}) => {
  if (!level) return null;

  const label = getDifficultyLabel(level);
  const color = getDifficultyColor(level);

  const fontSizes = {small: 10, medium: 13, large: 16};
  const paddings = {small: 3, medium: 5, large: 8};

  return (
    <View
      style={[
        styles.badge,
        {
          backgroundColor: color,
          paddingHorizontal: paddings[size],
          paddingVertical: paddings[size] - 1,
        },
      ]}>
      <Text
        style={[
          styles.text,
          {fontSize: fontSizes[size]},
        ]}>
        {label}
      </Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    borderRadius: 4,
    alignSelf: 'flex-start',
  },
  text: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
});

export default DifficultyBadge;
