import React from 'react';
import {View, ActivityIndicator, Text, StyleSheet} from 'react-native';

interface Props {
  message?: string;
}

const LoadingScreen: React.FC<Props> = ({message}) => {
  return (
    <View style={styles.container}>
      <ActivityIndicator size="large" color="#2E7D32" />
      {message ? <Text style={styles.text}>{message}</Text> : null}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
  },
  text: {
    marginTop: 12,
    fontSize: 16,
    color: '#555',
  },
});

export default LoadingScreen;
