import React from 'react';
import {View, Text, StyleSheet} from 'react-native';
import {getStatusColor} from '../utils/helpers';
import {useLang} from '../context/LangContext';

interface Props {
  status?: string;
}

const StatusBadge: React.FC<Props> = ({status}) => {
  const {t} = useLang();
  if (!status) return null;

  const color = getStatusColor(status);
  const labelMap: Record<string, string> = {
    ouvert: t.viaDetail.status.ouvert,
    ferme: t.viaDetail.status.ferme,
    ferme_definitif: t.viaDetail.status.ferme_definitif,
  };
  const label = labelMap[status] || status;

  return (
    <View style={[styles.badge, {backgroundColor: color}]}>
      <Text style={styles.text}>{label}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  badge: {
    borderRadius: 4,
    paddingHorizontal: 8,
    paddingVertical: 3,
    alignSelf: 'flex-start',
  },
  text: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '600',
  },
});

export default StatusBadge;
